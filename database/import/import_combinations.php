<?php

declare(strict_types=1);

require __DIR__ . '/import_helpers.php';

$options = getopt('', ['manifest:', 'execute', 'report::', 'help']);
if (isset($options['help']) || empty($options['manifest'])) {
    fwrite(STDOUT, "Import combinazioni IDEMA da manifest normalizzato\n\n");
    fwrite(STDOUT, "Uso:\n");
    fwrite(STDOUT, "  php database/import/import_combinations.php --manifest=/path/combinations.json [--execute] [--report=/path/report.json]\n");
    fwrite(STDOUT, "Senza --execute viene eseguito solo un dry-run.\n");
    exit(isset($options['help']) ? 0 : 1);
}

$manifestPath = (string) $options['manifest'];
$execute = array_key_exists('execute', $options);
$reportPath = isset($options['report']) && is_string($options['report']) && $options['report'] !== ''
    ? $options['report']
    : dirname(__DIR__, 2) . '/storage/logs/combinations-import-report.json';

if (!is_file($manifestPath) || !is_readable($manifestPath)) fail("Manifest non leggibile: {$manifestPath}");
$json = file_get_contents($manifestPath);
if ($json === false) fail("Impossibile leggere: {$manifestPath}");

try {
    $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fail('JSON non valido: ' . $e->getMessage());
}

$combinations = $manifest['combinations'] ?? null;
if (!is_array($combinations)) fail('Il manifest non contiene combinations[].');

$stats = [
    'combinations_seen' => 0,
    'combinations_resolved' => 0,
    'combinations_partial' => 0,
    'combinations_pending' => 0,
    'combinations_review' => 0,
    'items_seen' => 0,
    'items_resolved_product' => 0,
    'items_resolved_model' => 0,
    'items_pending' => 0,
    'items_review' => 0,
    'document_links_seen' => 0,
    'document_links_missing' => 0,
];
$warnings = [];

$pdo = null;
if ($execute) {
    require_once dirname(__DIR__, 2) . '/scripts/bootstrap.php';
    $pdo = \App\Core\Database::connection();
    $pdo->beginTransaction();
}

try {
    foreach ($combinations as $index => $combo) {
        if (!is_array($combo)) {
            $warnings[] = ['type' => 'invalid_combination', 'index' => $index];
            continue;
        }

        $sourceKey = trim((string) ($combo['source_key'] ?? ''));
        $subcategorySlug = trim((string) ($combo['subcategory_slug'] ?? ''));
        $sourceProductName = trim((string) ($combo['source_product_name'] ?? ''));
        $label = trim((string) ($combo['label'] ?? ''));
        $status = trim((string) ($combo['status'] ?? 'pending'));

        if ($sourceKey === '' || $subcategorySlug === '' || $sourceProductName === '' || $label === '') {
            $warnings[] = ['type' => 'missing_required_fields', 'index' => $index, 'label' => $label];
            continue;
        }
        if (!in_array($status, ['resolved', 'partial', 'pending', 'review'], true)) $status = 'review';

        $stats['combinations_seen']++;
        $stats['combinations_' . $status]++;

        $sourceProductId = null;
        $combinationId = null;
        if ($execute && $pdo instanceof PDO) {
            $sourceProductId = findProductId($pdo, $subcategorySlug, $sourceProductName);
            if ($sourceProductId === null) {
                $warnings[] = [
                    'type' => 'source_product_not_found',
                    'subcategory_slug' => $subcategorySlug,
                    'product' => $sourceProductName,
                    'label' => $label,
                ];
            }
            $combinationId = ensureCombination($pdo, $sourceProductId, $sourceKey, $label, $status);
        }

        $items = is_array($combo['items'] ?? null) ? $combo['items'] : [];
        foreach ($items as $itemIndex => $item) {
            if (!is_array($item)) continue;
            $rawCode = trim((string) ($item['raw_code'] ?? ''));
            $resolutionStatus = trim((string) ($item['resolution_status'] ?? 'pending'));
            $unitRole = normalizeUnitRole((string) ($item['unit_role'] ?? 'other'));
            $target = is_array($item['target'] ?? null) ? $item['target'] : null;

            $stats['items_seen']++;
            $productId = null;
            $modelId = null;
            $effectiveStatus = $resolutionStatus;

            if ($resolutionStatus === 'resolved_product' && $target !== null) {
                $targetSubcategory = trim((string) ($target['subcategory_slug'] ?? ''));
                $targetProduct = trim((string) ($target['product_name'] ?? ''));
                if ($execute && $pdo instanceof PDO) {
                    $productId = findProductId($pdo, $targetSubcategory, $targetProduct);
                    if ($productId === null) {
                        $effectiveStatus = 'pending';
                        $warnings[] = ['type' => 'target_product_not_found', 'raw_code' => $rawCode, 'product' => $targetProduct];
                    }
                }
            } elseif ($resolutionStatus === 'resolved_model' && $target !== null) {
                $targetSubcategory = trim((string) ($target['subcategory_slug'] ?? ''));
                $targetProduct = trim((string) ($target['product_name'] ?? ''));
                $targetModel = trim((string) ($target['model_code'] ?? ''));
                if ($execute && $pdo instanceof PDO) {
                    $targetProductId = findProductId($pdo, $targetSubcategory, $targetProduct);
                    if ($targetProductId !== null) $modelId = findModelId($pdo, $targetProductId, $targetModel);
                    if ($modelId === null) {
                        $effectiveStatus = 'pending';
                        $warnings[] = ['type' => 'target_model_not_found', 'raw_code' => $rawCode, 'model' => $targetModel];
                    }
                }
            }

            if (!in_array($effectiveStatus, ['resolved_product', 'resolved_model', 'pending', 'review'], true)) {
                $effectiveStatus = 'review';
            }
            $stats['items_' . $effectiveStatus]++;

            if ($execute && $pdo instanceof PDO && $combinationId !== null) {
                ensureCombinationItem(
                    $pdo,
                    $combinationId,
                    $productId,
                    $modelId,
                    $rawCode,
                    $effectiveStatus,
                    $unitRole,
                    $itemIndex
                );
            }
        }

        $documentUrls = is_array($combo['document_urls'] ?? null) ? $combo['document_urls'] : [];
        foreach ($documentUrls as $documentUrl) {
            $documentUrl = trim((string) $documentUrl);
            if ($documentUrl === '') continue;
            $stats['document_links_seen']++;

            if ($execute && $pdo instanceof PDO && $combinationId !== null) {
                $documentId = findDocumentIdByPath($pdo, $documentUrl);
                if ($documentId === null) {
                    $stats['document_links_missing']++;
                    $warnings[] = ['type' => 'combination_document_not_found', 'label' => $label, 'document_url' => $documentUrl];
                } else {
                    ensureCombinationDocument($pdo, $combinationId, $documentId);
                }
            }
        }
    }

    if ($execute && $pdo instanceof PDO) $pdo->commit();
} catch (Throwable $e) {
    if ($execute && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fail('Import combinazioni interrotto: ' . $e->getMessage());
}

$report = [
    'generated_at' => gmdate('c'),
    'mode' => $execute ? 'execute' : 'dry-run',
    'manifest' => $manifestPath,
    'stats' => $stats,
    'warnings' => $warnings,
];

$dir = dirname($reportPath);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) fail("Impossibile creare {$dir}");
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

fwrite(STDOUT, ($execute ? 'IMPORT COMBINAZIONI COMPLETATO' : 'DRY-RUN COMBINAZIONI COMPLETATO') . "\n");
fwrite(STDOUT, "Combinazioni: {$stats['combinations_seen']}\n");
fwrite(STDOUT, "  risolte: {$stats['combinations_resolved']}\n");
fwrite(STDOUT, "  parziali: {$stats['combinations_partial']}\n");
fwrite(STDOUT, "  pending: {$stats['combinations_pending']}\n");
fwrite(STDOUT, "  review: {$stats['combinations_review']}\n");
fwrite(STDOUT, "Item: {$stats['items_seen']}\n");
fwrite(STDOUT, "Report: {$reportPath}\n");

function findProductId(PDO $pdo, string $subcategorySlug, string $productName): ?int
{
    $slug = uniqueScopedSlug($subcategorySlug, slugify($productName));
    $stmt = $pdo->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function findModelId(PDO $pdo, int $productId, string $modelCode): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM product_models WHERE product_id = ? AND UPPER(code) = UPPER(?) LIMIT 1');
    $stmt->execute([$productId, $modelCode]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function findDocumentIdByPath(PDO $pdo, string $path): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM documents WHERE file_path = ? LIMIT 1');
    $stmt->execute([$path]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function ensureCombination(PDO $pdo, ?int $sourceProductId, string $sourceKey, string $label, string $status): int
{
    $stmt = $pdo->prepare('SELECT id FROM product_combinations WHERE source_key = ? LIMIT 1');
    $stmt->execute([$sourceKey]);
    $id = $stmt->fetchColumn();
    if ($id !== false) {
        $update = $pdo->prepare('UPDATE product_combinations SET source_product_id = ?, name = ?, source_label = ?, normalization_status = ? WHERE id = ?');
        $update->execute([$sourceProductId, $label, $label, $status, (int) $id]);
        return (int) $id;
    }

    $slug = 'combo-' . substr($sourceKey, 0, 24);
    $insert = $pdo->prepare('INSERT INTO product_combinations (source_product_id, name, source_label, slug, source_key, normalization_status) VALUES (?, ?, ?, ?, ?, ?)');
    $insert->execute([$sourceProductId, $label, $label, $slug, $sourceKey, $status]);
    return (int) $pdo->lastInsertId();
}

function ensureCombinationItem(PDO $pdo, int $combinationId, ?int $productId, ?int $modelId, string $rawCode, string $status, string $unitRole, int $sortOrder): void
{
    $stmt = $pdo->prepare('SELECT id FROM product_combination_items WHERE combination_id = ? AND sort_order = ? AND raw_code = ? LIMIT 1');
    $stmt->execute([$combinationId, $sortOrder, $rawCode]);
    $id = $stmt->fetchColumn();

    if ($id !== false) {
        $update = $pdo->prepare('UPDATE product_combination_items SET product_id = ?, model_id = ?, resolution_status = ?, unit_role = ? WHERE id = ?');
        $update->execute([$productId, $modelId, $status, $unitRole, (int) $id]);
        return;
    }

    $insert = $pdo->prepare('INSERT INTO product_combination_items (combination_id, product_id, model_id, raw_code, resolution_status, unit_role, quantity, sort_order) VALUES (?, ?, ?, ?, ?, ?, 1, ?)');
    $insert->execute([$combinationId, $productId, $modelId, $rawCode, $status, $unitRole, $sortOrder]);
}

function ensureCombinationDocument(PDO $pdo, int $combinationId, int $documentId): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO product_combination_documents (combination_id, document_id) VALUES (?, ?)');
    $stmt->execute([$combinationId, $documentId]);
}

function normalizeUnitRole(string $role): string
{
    return in_array($role, ['outdoor_unit', 'indoor_unit', 'accessory', 'other'], true) ? $role : 'other';
}
