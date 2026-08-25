<?php

declare(strict_types=1);

require __DIR__ . '/import_helpers.php';

$options = getopt('', ['registry::', 'execute', 'report::', 'help']);
if (isset($options['help'])) {
    fwrite(STDOUT, "Import modelli componenti verificati IDEMA\n\n");
    fwrite(STDOUT, "Uso:\n");
    fwrite(STDOUT, "  php database/import/import_verified_component_models.php [--registry=/path/verified_component_models.json] [--execute] [--report=/path/report.json]\n");
    fwrite(STDOUT, "Senza --execute viene eseguito solo un dry-run.\n");
    exit(0);
}

$registryPath = isset($options['registry']) && is_string($options['registry']) && $options['registry'] !== ''
    ? $options['registry']
    : __DIR__ . '/verified_component_models.json';
$execute = array_key_exists('execute', $options);
$reportPath = isset($options['report']) && is_string($options['report']) && $options['report'] !== ''
    ? $options['report']
    : dirname(__DIR__, 2) . '/storage/logs/verified-component-models-report.json';

if (!is_file($registryPath) || !is_readable($registryPath)) fail("Registry non leggibile: {$registryPath}");
$raw = file_get_contents($registryPath);
if ($raw === false) fail("Impossibile leggere registry: {$registryPath}");

try {
    $registry = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    fail('Registry JSON non valido: ' . $e->getMessage());
}

$missingSeries = is_array($registry['missing_series'] ?? null) ? $registry['missing_series'] : [];
$models = is_array($registry['models'] ?? null) ? $registry['models'] : [];

$stats = [
    'registry_models' => count($models),
    'missing_series_declared' => count($missingSeries),
    'series_to_create' => 0,
    'series_already_present' => 0,
    'models_to_create' => 0,
    'models_already_present' => 0,
    'errors' => 0,
];
$errors = [];
$actions = [];

$pdo = null;
if ($execute) {
    require_once dirname(__DIR__, 2) . '/scripts/bootstrap.php';
    $pdo = \App\Core\Database::connection();
    $pdo->beginTransaction();
}

try {
    foreach ($missingSeries as $series) {
        if (!is_array($series)) continue;
        $name = trim((string)($series['name'] ?? ''));
        $subcategorySlug = trim((string)($series['subcategory_slug'] ?? ''));
        if ($name === '' || $subcategorySlug === '') {
            $stats['errors']++;
            $errors[] = ['type' => 'invalid_missing_series', 'value' => $series];
            continue;
        }

        if ($execute && $pdo instanceof PDO) {
            $categoryId = findImportedSubcategoryId($pdo, $subcategorySlug);
            if ($categoryId === null) throw new RuntimeException("Sottocategoria non trovata: {$subcategorySlug}");

            $existingId = findProductByNameInCategory($pdo, $categoryId, $name);
            if ($existingId !== null) {
                $stats['series_already_present']++;
                continue;
            }

            $productId = ensureProduct(
                $pdo,
                $categoryId,
                $name,
                uniqueScopedSlug($subcategorySlug, slugify($name)),
                '',
                (string)($series['product_role'] ?? 'indoor_unit'),
                isset($series['refrigerant']) ? (string)$series['refrigerant'] : 'R32',
                (string)($series['status'] ?? 'active'),
                null,
                999
            );
            $stats['series_to_create']++;
            $actions[] = ['type' => 'create_series', 'name' => $name, 'product_id' => $productId];
        } else {
            $stats['series_to_create']++;
            $actions[] = ['type' => 'create_series', 'name' => $name, 'subcategory_slug' => $subcategorySlug];
        }
    }

    foreach ($models as $index => $model) {
        if (!is_array($model)) continue;
        $code = trim((string)($model['code'] ?? ''));
        $productName = trim((string)($model['target_product_name'] ?? ''));
        $subcategorySlug = trim((string)($model['subcategory_slug'] ?? ''));
        if ($code === '' || $productName === '' || $subcategorySlug === '') {
            $stats['errors']++;
            $errors[] = ['type' => 'invalid_model_mapping', 'value' => $model];
            continue;
        }

        if ($execute && $pdo instanceof PDO) {
            $categoryId = findImportedSubcategoryId($pdo, $subcategorySlug);
            if ($categoryId === null) throw new RuntimeException("Sottocategoria non trovata: {$subcategorySlug}");
            $productId = findProductByNameInCategory($pdo, $categoryId, $productName);
            if ($productId === null) throw new RuntimeException("Prodotto serie non trovato: {$productName} ({$subcategorySlug})");

            $stmt = $pdo->prepare('SELECT id FROM product_models WHERE product_id = ? AND UPPER(code) = UPPER(?) LIMIT 1');
            $stmt->execute([$productId, $code]);
            if ($stmt->fetchColumn() !== false) {
                $stats['models_already_present']++;
                continue;
            }

            $modelId = ensureModel($pdo, $productId, $code, $index);
            $stats['models_to_create']++;
            $actions[] = ['type' => 'create_model', 'code' => $code, 'product' => $productName, 'model_id' => $modelId];
        } else {
            $stats['models_to_create']++;
            $actions[] = ['type' => 'create_model', 'code' => $code, 'product' => $productName, 'subcategory_slug' => $subcategorySlug];
        }
    }

    if ($execute && $pdo instanceof PDO) $pdo->commit();
} catch (Throwable $e) {
    if ($execute && $pdo instanceof PDO && $pdo->inTransaction()) $pdo->rollBack();
    fail('Import modelli verificati interrotto: ' . $e->getMessage());
}

$report = [
    'generated_at' => gmdate('c'),
    'mode' => $execute ? 'execute' : 'dry-run',
    'registry' => $registryPath,
    'stats' => $stats,
    'errors' => $errors,
    'actions' => $actions,
];

$dir = dirname($reportPath);
if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) fail("Impossibile creare {$dir}");
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

fwrite(STDOUT, ($execute ? 'IMPORT' : 'DRY-RUN') . " MODELLI VERIFICATI COMPLETATO\n");
fwrite(STDOUT, "Registry modelli: {$stats['registry_models']}\n");
fwrite(STDOUT, "Serie mancanti dichiarate: {$stats['missing_series_declared']}\n");
fwrite(STDOUT, "Modelli da creare: {$stats['models_to_create']}\n");
fwrite(STDOUT, "Errori: {$stats['errors']}\n");
fwrite(STDOUT, "Report: {$reportPath}\n");

function findImportedSubcategoryId(PDO $pdo, string $rawSubcategorySlug): ?int
{
    $suffix = '--' . slugify($rawSubcategorySlug);
    $stmt = $pdo->prepare('SELECT id FROM product_categories WHERE slug = ? OR slug LIKE ? ORDER BY id LIMIT 1');
    $stmt->execute([$rawSubcategorySlug, '%' . $suffix]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}

function findProductByNameInCategory(PDO $pdo, int $categoryId, string $name): ?int
{
    $stmt = $pdo->prepare('SELECT id FROM products WHERE category_id = ? AND UPPER(name) = UPPER(?) LIMIT 1');
    $stmt->execute([$categoryId, $name]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int)$id;
}
