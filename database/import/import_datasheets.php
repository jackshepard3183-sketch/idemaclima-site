<?php

declare(strict_types=1);

/**
 * IDEMA Clima - import una tantum da src/data/datasheets.ts (Lovable)
 *
 * Uso:
 *   php database/import/import_datasheets.php --source=/percorso/datasheets.ts
 *   php database/import/import_datasheets.php --source=/percorso/datasheets.ts --execute
 *   php database/import/import_datasheets.php --source=/percorso/datasheets.ts --report=/tmp/report.json
 *
 * Default: DRY RUN. Nessuna scrittura sul database senza --execute.
 */

require __DIR__ . '/import_helpers.php';

const ROOT_CATEGORY_NAMES = [
    'linea-residenziale-r32' => 'Linea Residenziale R32',
    'linea-commerciale-r32' => 'Linea Commerciale R32',
    'linea-vrf' => 'Linea VRF',
    'linea-idronica' => 'Linea Idronica',
    'altri-prodotti' => 'Altri Prodotti',
];

$options = getopt('', ['source:', 'execute', 'report::', 'help']);
if (isset($options['help']) || empty($options['source'])) {
    fwrite(STDOUT, "Import IDEMA datasheets.ts -> MySQL\n\n");
    fwrite(STDOUT, "Uso:\n");
    fwrite(STDOUT, "  php database/import/import_datasheets.php --source=/path/datasheets.ts [--execute] [--report=/path/report.json]\n\n");
    fwrite(STDOUT, "Senza --execute viene eseguito solo un dry-run.\n");
    exit(isset($options['help']) ? 0 : 1);
}

$sourcePath = (string) $options['source'];
$execute = array_key_exists('execute', $options);
$reportPath = isset($options['report']) && is_string($options['report']) && $options['report'] !== ''
    ? $options['report']
    : dirname(__DIR__, 2) . '/storage/logs/datasheets-import-report.json';

if (!is_file($sourcePath) || !is_readable($sourcePath)) {
    fail("File sorgente non leggibile: {$sourcePath}");
}

$source = file_get_contents($sourcePath);
if ($source === false) {
    fail("Impossibile leggere il file sorgente: {$sourcePath}");
}

try {
    $payload = extractDatasheetsObject($source);
} catch (Throwable $e) {
    fail('Parsing fallito: ' . $e->getMessage());
}

$stats = [
    'root_categories' => 0,
    'subcategories' => 0,
    'empty_subcategories' => 0,
    'products' => 0,
    'products_unavailable' => 0,
    'products_without_files' => 0,
    'model_candidate_occurrences' => 0,
    'model_pairs_unique' => 0,
    'model_labels_distinct' => 0,
    'document_entries' => 0,
    'unique_documents' => 0,
    'duplicate_document_occurrences' => 0,
    'urls_used_once' => 0,
    'urls_reused' => 0,
    'entries_pointing_to_reused_urls' => 0,
    'document_links' => 0,
    'combination_occurrences' => 0,
    'combination_labels_distinct' => 0,
    'ambiguous_labels' => 0,
];

$warnings = [];
$normalization = [];
$documentUrlCounts = [];
$modelPairs = [];
$modelLabels = [];
$combinationLabels = [];
$groupLabelCounts = [];

$pdo = null;
if ($execute) {
    $projectRoot = dirname(__DIR__, 2);
    require_once $projectRoot . '/scripts/bootstrap.php';
    $pdo = \App\Core\Database::connection();
    $pdo->beginTransaction();
}

try {
    foreach ($payload as $rootSlug => $subcategories) {
        if (!is_string($rootSlug) || !is_array($subcategories)) {
            $warnings[] = ['type' => 'invalid_root', 'value' => $rootSlug];
            continue;
        }

        $rootName = ROOT_CATEGORY_NAMES[$rootSlug] ?? humanizeSlug($rootSlug);
        $rootId = $execute ? ensureCategory($pdo, null, $rootName, $rootSlug) : null;
        $stats['root_categories']++;

        foreach ($subcategories as $subcategoryIndex => $subcategory) {
            if (!is_array($subcategory)) continue;

            $subcategoryName = trim((string) ($subcategory['subcategoryName'] ?? ''));
            $subcategorySlug = trim((string) ($subcategory['subcategorySlug'] ?? ''));
            if ($subcategoryName === '' || $subcategorySlug === '') {
                $warnings[] = [
                    'type' => 'invalid_subcategory',
                    'root' => $rootSlug,
                    'index' => $subcategoryIndex,
                ];
                continue;
            }

            $subCategoryId = $execute
                ? ensureCategory($pdo, $rootId, $subcategoryName, uniqueScopedSlug($rootSlug, $subcategorySlug))
                : null;
            $stats['subcategories']++;

            $products = $subcategory['products'] ?? [];
            if (!is_array($products)) $products = [];
            if ($products === []) {
                $stats['empty_subcategories']++;
                $normalization[] = [
                    'type' => 'empty_subcategory',
                    'root_category' => $rootName,
                    'subcategory' => $subcategoryName,
                    'subcategory_slug' => $subcategorySlug,
                ];
            }

            foreach ($products as $productIndex => $product) {
                if (!is_array($product)) continue;

                $productName = trim((string) ($product['name'] ?? ''));
                if ($productName === '') {
                    $warnings[] = [
                        'type' => 'product_without_name',
                        'subcategory' => $subcategoryName,
                        'index' => $productIndex,
                    ];
                    continue;
                }

                $productSlug = uniqueScopedSlug($subcategorySlug, slugify($productName));
                $description = trim((string) ($product['description'] ?? ''));
                $image = isset($product['image']) && is_string($product['image']) ? trim($product['image']) : null;
                $unavailable = (bool) ($product['unavailable'] ?? false);
                $status = $unavailable ? 'unavailable' : 'active';
                $role = inferProductRole($subcategoryName, $productName);
                $refrigerant = inferRefrigerant($productName . ' ' . $description);

                $productId = $execute
                    ? ensureProduct(
                        $pdo,
                        $subCategoryId,
                        $productName,
                        $productSlug,
                        $description,
                        $role,
                        $refrigerant,
                        $status,
                        $image,
                        $productIndex
                    )
                    : null;
                $stats['products']++;
                if ($unavailable) $stats['products_unavailable']++;

                if ($role === 'complete_system' && preg_match('/(UNIT[ÀA]\s+(INTERNE|ESTERNE)|ACCESSORI|SERBATOI?)/iu', $subcategoryName)) {
                    $normalization[] = [
                        'type' => 'role_review',
                        'product' => $productName,
                        'subcategory' => $subcategoryName,
                        'inferred_role' => $role,
                    ];
                }

                $groups = $product['groups'] ?? [];
                if (!is_array($groups)) $groups = [];
                $productFileCount = 0;

                foreach ($groups as $group) {
                    if (!is_array($group)) continue;
                    $groupLabel = trim((string) ($group['label'] ?? 'ALTRO'));
                    $files = $group['files'] ?? [];
                    if (!is_array($files)) continue;

                    foreach ($files as $fileIndex => $file) {
                        if (!is_array($file)) continue;

                        $label = trim((string) ($file['model'] ?? ''));
                        $url = trim((string) ($file['url'] ?? ''));
                        if ($url === '') {
                            $warnings[] = [
                                'type' => 'document_without_url',
                                'product' => $productName,
                                'group' => $groupLabel,
                                'label' => $label,
                            ];
                            continue;
                        }

                        $productFileCount++;
                        $stats['document_entries']++;
                        $stats['document_links']++;
                        $documentUrlCounts[$url] = ($documentUrlCounts[$url] ?? 0) + 1;
                        $groupLabelCounts[$groupLabel] = ($groupLabelCounts[$groupLabel] ?? 0) + 1;

                        $documentType = classifyDocumentType($groupLabel, $label);
                        $filename = filenameFromUrl($url);
                        $title = buildDocumentTitle($documentType, $productName, $label);

                        $targetModelId = null;
                        $isCombination = str_contains($label, '+');
                        if ($isCombination) {
                            $stats['combination_occurrences']++;
                            $combinationLabels[$label] = true;
                            $normalization[] = [
                                'type' => 'combination',
                                'root_category' => $rootName,
                                'subcategory' => $subcategoryName,
                                'product' => $productName,
                                'label' => $label,
                                'parts' => array_values(array_filter(array_map('trim', explode('+', $label)))),
                                'document_url' => $url,
                            ];
                        } elseif (shouldCreateModel($label, $productName, $groupLabel)) {
                            $stats['model_candidate_occurrences']++;
                            $pairKey = $productSlug . '|' . strtoupper(normalizeWhitespace($label));
                            $modelPairs[$pairKey] = true;
                            $modelLabels[strtoupper(normalizeWhitespace($label))] = $label;
                            $targetModelId = $execute ? ensureModel($pdo, $productId, $label, $fileIndex) : null;
                        } elseif ($label !== '' && !isGenericDocumentLabel($label) && strcasecmp($label, $productName) !== 0) {
                            $stats['ambiguous_labels']++;
                            $normalization[] = [
                                'type' => 'ambiguous_document_label',
                                'root_category' => $rootName,
                                'subcategory' => $subcategoryName,
                                'product' => $productName,
                                'group' => $groupLabel,
                                'label' => $label,
                                'document_url' => $url,
                            ];
                        }

                        if ($execute) {
                            $documentTypeId = ensureDocumentType($pdo, $documentType);
                            $documentId = ensureDocument($pdo, $documentTypeId, $title, $filename, $url, $fileIndex);
                            ensureDocumentLink($pdo, $documentId, null, $productId, $targetModelId);
                        }
                    }
                }

                if ($productFileCount === 0) {
                    $stats['products_without_files']++;
                    $normalization[] = [
                        'type' => 'product_without_files',
                        'root_category' => $rootName,
                        'subcategory' => $subcategoryName,
                        'product' => $productName,
                    ];
                }
            }
        }
    }

    $stats['model_pairs_unique'] = count($modelPairs);
    $stats['model_labels_distinct'] = count($modelLabels);
    $stats['combination_labels_distinct'] = count($combinationLabels);
    $stats['unique_documents'] = count($documentUrlCounts);

    foreach ($documentUrlCounts as $count) {
        if ($count === 1) {
            $stats['urls_used_once']++;
        } else {
            $stats['urls_reused']++;
            $stats['entries_pointing_to_reused_urls'] += $count;
        }
    }
    $stats['duplicate_document_occurrences'] = $stats['document_entries'] - $stats['unique_documents'];

    if ($execute && $pdo instanceof PDO) {
        $pdo->commit();
    }
} catch (Throwable $e) {
    if ($execute && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fail('Import interrotto: ' . $e->getMessage());
}

arsort($groupLabelCounts);
arsort($documentUrlCounts);
$topReusedDocuments = [];
foreach ($documentUrlCounts as $url => $count) {
    if ($count < 2) continue;
    $topReusedDocuments[] = [
        'url' => $url,
        'filename' => filenameFromUrl($url),
        'count' => $count,
    ];
    if (count($topReusedDocuments) >= 20) break;
}

$report = [
    'generated_at' => gmdate('c'),
    'mode' => $execute ? 'execute' : 'dry-run',
    'source' => $sourcePath,
    'stats' => $stats,
    'group_label_counts' => $groupLabelCounts,
    'top_reused_documents' => $topReusedDocuments,
    'warnings' => $warnings,
    'normalization_queue' => $normalization,
];

$reportDir = dirname($reportPath);
if (!is_dir($reportDir) && !mkdir($reportDir, 0775, true) && !is_dir($reportDir)) {
    fail("Impossibile creare la cartella report: {$reportDir}");
}

file_put_contents(
    $reportPath,
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
);

fwrite(STDOUT, ($execute ? 'IMPORT COMPLETATO' : 'DRY-RUN COMPLETATO') . "\n");
fwrite(STDOUT, "Categorie root: {$stats['root_categories']}\n");
fwrite(STDOUT, "Sottocategorie: {$stats['subcategories']}\n");
fwrite(STDOUT, "Prodotti: {$stats['products']} ({$stats['products_unavailable']} non disponibili)\n");
fwrite(STDOUT, "Modelli candidati: {$stats['model_candidate_occurrences']} occorrenze / {$stats['model_pairs_unique']} coppie prodotto+modello uniche\n");
fwrite(STDOUT, "Documenti: {$stats['document_entries']} collegamenti / {$stats['unique_documents']} PDF unici / {$stats['duplicate_document_occurrences']} riutilizzi\n");
fwrite(STDOUT, "Combinazioni da normalizzare: {$stats['combination_occurrences']} occorrenze / {$stats['combination_labels_distinct']} etichette distinte\n");
fwrite(STDOUT, "Etichette ambigue: {$stats['ambiguous_labels']}\n");
fwrite(STDOUT, "Report: {$reportPath}\n");
