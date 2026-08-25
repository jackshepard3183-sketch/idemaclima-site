<?php

declare(strict_types=1);

$options = getopt('', ['manifest:', 'registry::', 'output::', 'help']);
if (isset($options['help']) || empty($options['manifest'])) {
    fwrite(STDOUT, "Applica modelli verificati al manifest combinazioni IDEMA\n\n");
    fwrite(STDOUT, "Uso:\n");
    fwrite(STDOUT, "  php database/import/apply_verified_models_to_combinations.php --manifest=/path/combinations.json [--registry=/path/verified_component_models.json] [--output=/path/combinations-verified.json]\n");
    exit(isset($options['help']) ? 0 : 1);
}

$manifestPath = (string)$options['manifest'];
$registryPath = isset($options['registry']) && is_string($options['registry']) && $options['registry'] !== ''
    ? $options['registry']
    : __DIR__ . '/verified_component_models.json';
$outputPath = isset($options['output']) && is_string($options['output']) && $options['output'] !== ''
    ? $options['output']
    : dirname(__DIR__, 2) . '/storage/logs/datasheets-combinations-verified.json';

foreach ([$manifestPath, $registryPath] as $path) {
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "ERRORE: file non leggibile: {$path}\n");
        exit(1);
    }
}

try {
    $manifest = json_decode((string)file_get_contents($manifestPath), true, flags: JSON_THROW_ON_ERROR);
    $registry = json_decode((string)file_get_contents($registryPath), true, flags: JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERRORE JSON: ' . $e->getMessage() . "\n");
    exit(1);
}

$verified = [];
foreach (($registry['models'] ?? []) as $model) {
    if (!is_array($model)) continue;
    $code = strtoupper(trim((string)($model['code'] ?? '')));
    if ($code === '') continue;
    $verified[$code] = $model;
}

$summary = [
    'combinations_seen' => 0,
    'items_seen' => 0,
    'pending_before' => 0,
    'resolved_by_registry' => 0,
    'pending_after' => 0,
    'combinations_resolved' => 0,
    'combinations_partial' => 0,
    'combinations_pending' => 0,
    'combinations_review' => 0,
];

$combinations = is_array($manifest['combinations'] ?? null) ? $manifest['combinations'] : [];
foreach ($combinations as &$combo) {
    if (!is_array($combo)) continue;
    $summary['combinations_seen']++;
    $hasPending = false;
    $hasReview = false;
    $hasResolved = false;

    $items = is_array($combo['items'] ?? null) ? $combo['items'] : [];
    foreach ($items as &$item) {
        if (!is_array($item)) continue;
        $summary['items_seen']++;
        $status = (string)($item['resolution_status'] ?? 'pending');
        if ($status === 'pending') {
            $summary['pending_before']++;
            $code = strtoupper(trim((string)($item['raw_code'] ?? '')));
            if (isset($verified[$code])) {
                $mapping = $verified[$code];
                $item['resolution_status'] = 'resolved_model';
                $item['unit_role'] = 'indoor_unit';
                $item['target'] = [
                    'kind' => 'model',
                    'subcategory_slug' => (string)$mapping['subcategory_slug'],
                    'product_name' => (string)$mapping['target_product_name'],
                    'model_code' => (string)$mapping['code'],
                ];
                $item['verification'] = [
                    'source' => 'verified_component_models.json',
                    'evidence_groups' => $mapping['evidence_groups'] ?? [],
                ];
                $summary['resolved_by_registry']++;
                $status = 'resolved_model';
            }
        }

        if ($status === 'pending') {
            $hasPending = true;
            $summary['pending_after']++;
        } elseif ($status === 'review') {
            $hasReview = true;
        } else {
            $hasResolved = true;
        }
    }
    unset($item);

    $comboStatus = $hasReview ? 'review' : ($hasPending ? ($hasResolved ? 'partial' : 'pending') : 'resolved');
    $combo['status'] = $comboStatus;
    $summary['combinations_' . $comboStatus]++;
    $combo['verification_applied'] = true;
    $combo['verification_registry'] = basename($registryPath);
    $combo['items'] = $items;
}
unset($combo);

$manifest['generated_at'] = gmdate('c');
$manifest['mode'] = 'normalization-with-verified-models';
$manifest['verification_registry'] = $registryPath;
$manifest['verification_summary'] = $summary;
$manifest['combinations'] = $combinations;

$dir = dirname($outputPath);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
    fwrite(STDERR, "ERRORE: impossibile creare {$dir}\n");
    exit(1);
}
file_put_contents($outputPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

fwrite(STDOUT, "APPLICAZIONE REGISTRY COMPLETATA\n");
fwrite(STDOUT, "Pending prima: {$summary['pending_before']}\n");
fwrite(STDOUT, "Risolti dal registry: {$summary['resolved_by_registry']}\n");
fwrite(STDOUT, "Pending dopo: {$summary['pending_after']}\n");
fwrite(STDOUT, "Combinazioni risolte: {$summary['combinations_resolved']}\n");
fwrite(STDOUT, "Output: {$outputPath}\n");
