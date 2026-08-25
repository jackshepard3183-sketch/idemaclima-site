<?php

declare(strict_types=1);

require __DIR__ . '/import_helpers.php';

$options = getopt('', ['source:', 'output::', 'help']);
if (isset($options['help']) || empty($options['source'])) {
    fwrite(STDOUT, "Normalizzazione combinazioni IDEMA\n\n");
    fwrite(STDOUT, "Uso:\n");
    fwrite(STDOUT, "  php database/import/normalize_combinations.php --source=/path/datasheets.ts [--output=/path/combinations.json]\n");
    exit(isset($options['help']) ? 0 : 1);
}

$sourcePath = (string) $options['source'];
$outputPath = isset($options['output']) && is_string($options['output']) && $options['output'] !== ''
    ? $options['output']
    : dirname(__DIR__, 2) . '/storage/logs/datasheets-combinations.json';

if (!is_file($sourcePath) || !is_readable($sourcePath)) fail("File sorgente non leggibile: {$sourcePath}");
$source = file_get_contents($sourcePath);
if ($source === false) fail("Impossibile leggere: {$sourcePath}");

try {
    $payload = extractDatasheetsObject($source);
} catch (Throwable $e) {
    fail('Parsing fallito: ' . $e->getMessage());
}

$productIndex = [];
$modelIndex = [];
$combinationOccurrences = [];

foreach ($payload as $rootSlug => $subcategories) {
    if (!is_array($subcategories)) continue;

    foreach ($subcategories as $subcategory) {
        if (!is_array($subcategory)) continue;
        $subcategorySlug = trim((string) ($subcategory['subcategorySlug'] ?? ''));
        $subcategoryName = trim((string) ($subcategory['subcategoryName'] ?? ''));
        $products = is_array($subcategory['products'] ?? null) ? $subcategory['products'] : [];

        foreach ($products as $product) {
            if (!is_array($product)) continue;
            $productName = trim((string) ($product['name'] ?? ''));
            if ($productName === '') continue;
            $productRole = inferProductRole($subcategoryName, $productName);

            foreach (deterministicProductAliases($productName) as $alias) {
                $productIndex[$alias][] = [
                    'root_slug' => (string) $rootSlug,
                    'subcategory_slug' => $subcategorySlug,
                    'subcategory_name' => $subcategoryName,
                    'product_name' => $productName,
                    'product_role' => $productRole,
                ];
            }

            $groups = is_array($product['groups'] ?? null) ? $product['groups'] : [];
            foreach ($groups as $group) {
                if (!is_array($group)) continue;
                $groupLabel = trim((string) ($group['label'] ?? ''));
                $groupUpper = strtoupper(normalizeWhitespace($groupLabel));
                if (!str_contains($groupUpper, 'SCHED') && !str_contains($groupUpper, 'RESE')) continue;

                $files = is_array($group['files'] ?? null) ? $group['files'] : [];
                foreach ($files as $file) {
                    if (!is_array($file)) continue;
                    $label = trim((string) ($file['model'] ?? ''));
                    $url = trim((string) ($file['url'] ?? ''));

                    if (str_contains($label, '+')) {
                        $sourceKey = strtoupper(normalizeWhitespace($label)) . '|' . $subcategorySlug . '|' . strtoupper($productName);
                        if (!isset($combinationOccurrences[$sourceKey])) {
                            $combinationOccurrences[$sourceKey] = [
                                'root_slug' => (string) $rootSlug,
                                'subcategory_slug' => $subcategorySlug,
                                'subcategory_name' => $subcategoryName,
                                'source_product_name' => $productName,
                                'label' => normalizeWhitespace($label),
                                'occurrences' => 0,
                                'document_urls' => [],
                            ];
                        }
                        $combinationOccurrences[$sourceKey]['occurrences']++;
                        if ($url !== '') $combinationOccurrences[$sourceKey]['document_urls'][$url] = true;
                        continue;
                    }

                    if (shouldCreateModel($label, $productName, $groupLabel)) {
                        $code = strtoupper(normalizeWhitespace($label));
                        $modelIndex[$code][] = [
                            'root_slug' => (string) $rootSlug,
                            'subcategory_slug' => $subcategorySlug,
                            'subcategory_name' => $subcategoryName,
                            'product_name' => $productName,
                            'product_role' => $productRole,
                            'model_code' => normalizeWhitespace($label),
                        ];
                    }
                }
            }
        }
    }
}

$manifest = [];
$summary = [
    'scoped_combinations' => 0,
    'distinct_labels' => 0,
    'occurrences' => 0,
    'resolved' => 0,
    'partial' => 0,
    'pending' => 0,
    'review' => 0,
    'items_total' => 0,
    'items_resolved_product' => 0,
    'items_resolved_model' => 0,
    'items_pending' => 0,
    'items_review' => 0,
];
$distinctLabels = [];

foreach ($combinationOccurrences as $sourceKey => $combo) {
    $items = [];
    $hasPending = false;
    $hasReview = false;
    $hasResolved = false;
    $parts = array_values(array_filter(array_map('normalizeWhitespace', explode('+', (string) $combo['label']))));

    foreach ($parts as $index => $rawCode) {
        $codeKey = strtoupper($rawCode);
        $productCandidates = uniqueCandidates($productIndex[$codeKey] ?? []);
        $modelCandidates = uniqueCandidates($modelIndex[$codeKey] ?? []);

        $resolved = resolveCandidate($productCandidates, (string) $combo['subcategory_slug']);
        $item = [
            'raw_code' => $rawCode,
            'sort_order' => $index,
            'unit_role' => 'other',
            'resolution_status' => 'pending',
            'target' => null,
        ];

        if ($resolved['status'] === 'resolved') {
            $candidate = $resolved['candidate'];
            $item['resolution_status'] = 'resolved_product';
            $item['unit_role'] = (string) ($candidate['product_role'] ?? 'other');
            $item['target'] = [
                'kind' => 'product',
                'subcategory_slug' => $candidate['subcategory_slug'],
                'product_name' => $candidate['product_name'],
            ];
            $hasResolved = true;
            $summary['items_resolved_product']++;
        } elseif ($resolved['status'] === 'ambiguous') {
            $item['resolution_status'] = 'review';
            $item['candidates'] = $resolved['candidates'];
            $hasReview = true;
            $summary['items_review']++;
        } else {
            $resolvedModel = resolveCandidate($modelCandidates, (string) $combo['subcategory_slug']);
            if ($resolvedModel['status'] === 'resolved') {
                $candidate = $resolvedModel['candidate'];
                $item['resolution_status'] = 'resolved_model';
                $item['unit_role'] = (string) ($candidate['product_role'] ?? 'other');
                $item['target'] = [
                    'kind' => 'model',
                    'subcategory_slug' => $candidate['subcategory_slug'],
                    'product_name' => $candidate['product_name'],
                    'model_code' => $candidate['model_code'],
                ];
                $hasResolved = true;
                $summary['items_resolved_model']++;
            } elseif ($resolvedModel['status'] === 'ambiguous') {
                $item['resolution_status'] = 'review';
                $item['candidates'] = $resolvedModel['candidates'];
                $hasReview = true;
                $summary['items_review']++;
            } else {
                $hasPending = true;
                $summary['items_pending']++;
            }
        }

        $summary['items_total']++;
        $items[] = $item;
    }

    $status = $hasReview ? 'review' : ($hasPending ? ($hasResolved ? 'partial' : 'pending') : 'resolved');
    $summary[$status]++;
    $summary['scoped_combinations']++;
    $summary['occurrences'] += (int) $combo['occurrences'];
    $distinctLabels[strtoupper((string) $combo['label'])] = true;

    $manifest[] = [
        'source_key' => hash('sha256', $sourceKey),
        'root_slug' => $combo['root_slug'],
        'subcategory_slug' => $combo['subcategory_slug'],
        'source_product_name' => $combo['source_product_name'],
        'label' => $combo['label'],
        'status' => $status,
        'occurrences' => $combo['occurrences'],
        'document_urls' => array_keys($combo['document_urls']),
        'items' => $items,
    ];
}

$summary['distinct_labels'] = count($distinctLabels);

$report = [
    'generated_at' => gmdate('c'),
    'mode' => 'normalization-dry-run',
    'source' => $sourcePath,
    'rules' => [
        'exact_case_insensitive_only' => true,
        'fuzzy_matching' => false,
        'product_aliases' => ['original', 'full slash alternatives', 'compact M/T alternatives', 'numeric capacity list alternatives'],
        'unresolved_policy' => 'preserve raw_code and mark pending',
    ],
    'summary' => $summary,
    'combinations' => $manifest,
];

$dir = dirname($outputPath);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) fail("Impossibile creare {$dir}");
file_put_contents($outputPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

fwrite(STDOUT, "NORMALIZZAZIONE COMPLETATA (DRY RUN)\n");
fwrite(STDOUT, "Combinazioni contestualizzate: {$summary['scoped_combinations']}\n");
fwrite(STDOUT, "Etichette distinte: {$summary['distinct_labels']}\n");
fwrite(STDOUT, "Occorrenze: {$summary['occurrences']}\n");
fwrite(STDOUT, "Risolte: {$summary['resolved']}\n");
fwrite(STDOUT, "Parziali: {$summary['partial']}\n");
fwrite(STDOUT, "Pending: {$summary['pending']}\n");
fwrite(STDOUT, "Review: {$summary['review']}\n");
fwrite(STDOUT, "Output: {$outputPath}\n");

function deterministicProductAliases(string $productName): array
{
    $base = strtoupper(normalizeWhitespace($productName));
    $aliases = [$base => true];
    $parts = str_contains($base, ' / ') ? array_map('trim', explode(' / ', $base)) : [$base];

    foreach ($parts as $part) {
        if ($part === '') continue;
        $aliases[$part] = true;

        if (preg_match('/^(.*?)-(\d+)([A-Z])\/([A-Z])(-.*)$/u', $part, $m)) {
            $aliases[$m[1] . '-' . $m[2] . $m[3] . $m[5]] = true;
            $aliases[$m[1] . '-' . $m[2] . $m[4] . $m[5]] = true;
        }

        if (preg_match('/^(.*?-)((?:\d+-){1,}\d+)([A-Z].*)$/u', $part, $m)) {
            foreach (explode('-', $m[2]) as $capacity) {
                if ($capacity !== '') $aliases[$m[1] . $capacity . $m[3]] = true;
            }
        }
    }

    return array_keys($aliases);
}

function uniqueCandidates(array $candidates): array
{
    $unique = [];
    foreach ($candidates as $candidate) {
        $key = implode('|', [
            strtolower((string) ($candidate['subcategory_slug'] ?? '')),
            strtolower((string) ($candidate['product_name'] ?? '')),
            strtolower((string) ($candidate['model_code'] ?? '')),
        ]);
        $unique[$key] = $candidate;
    }
    return array_values($unique);
}

function resolveCandidate(array $candidates, string $subcategorySlug): array
{
    if ($candidates === []) return ['status' => 'unresolved'];

    $same = array_values(array_filter(
        $candidates,
        static fn(array $candidate): bool => strcasecmp((string) ($candidate['subcategory_slug'] ?? ''), $subcategorySlug) === 0
    ));

    if (count($same) === 1) return ['status' => 'resolved', 'candidate' => $same[0]];
    if (count($same) > 1) return ['status' => 'ambiguous', 'candidates' => $same];
    if (count($candidates) === 1) return ['status' => 'resolved', 'candidate' => $candidates[0]];

    return ['status' => 'ambiguous', 'candidates' => $candidates];
}
