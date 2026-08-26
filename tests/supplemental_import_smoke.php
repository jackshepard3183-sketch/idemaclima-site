<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$registryPath = $root . '/database/import/terminali_idronici_registry.json';
$manifestPath = $root . '/database/import/supplemental_document_manifest.csv';

function fail(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

$raw = file_get_contents($registryPath);
if ($raw === false) fail('Registry terminali non leggibile.');
$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
$categories = $data['categories'] ?? [];
if (count($categories) !== 1) fail('Il registry deve contenere una categoria.');
$category = $categories[0];
if (($category['slug'] ?? '') !== 'linea-idronica-terminali-idronici') fail('Slug categoria terminali non valido.');

$products = $category['products'] ?? [];
if (count($products) !== 21) fail('Attesi 21 prodotti/serie terminali.');
$productMap = [];
$modelCount = 0;
foreach ($products as $product) {
    $slug = (string)($product['slug'] ?? '');
    if ($slug === '' || isset($productMap[$slug])) fail('Slug prodotto mancante o duplicato: ' . $slug);
    $models = array_values($product['models'] ?? []);
    $productMap[$slug] = array_fill_keys($models, true);
    $modelCount += count($models);
}
if ($modelCount !== 38) fail('Attesi 38 codici modello nel registry.');

$fh = fopen($manifestPath, 'rb');
if (!$fh) fail('Manifest supplementare non leggibile.');
$header = fgetcsv($fh);
if (!$header) fail('Manifest supplementare vuoto.');
$required = ['source_url','title','type_slug','group_label','target_filename','category_slug','product_slug','model_code','notes'];
if (array_diff($required, $header)) fail('Header manifest supplementare incompleto.');

$rows = [];
while (($line = fgetcsv($fh)) !== false) {
    if (count($line) !== count($header)) fail('Riga CSV con numero colonne errato.');
    $rows[] = array_combine($header, $line);
}
fclose($fh);

if (count($rows) !== 57) fail('Attese 57 righe nel manifest supplementare.');
$urls = array_column($rows, 'source_url');
if (count(array_unique($urls)) !== 57) fail('Gli URL sorgente devono essere univoci.');

$terminalRows = array_values(array_filter(
    $rows,
    static fn(array $row): bool => ($row['category_slug'] ?? '') === 'linea-idronica-terminali-idronici'
));
if (count($terminalRows) !== 55) fail('Attesi 55 documenti terminali idronici.');

$filenames = array_fill_keys(array_column($rows, 'target_filename'), true);
if (!isset($filenames['C-CATALOGO-CAC-V8-2026.pdf'])) fail('Catalogo V8 2026 assente.');
if (!isset($filenames['MODULO-RICHIESTA-PLENUM-2022.pdf'])) fail('Modulo plenum assente.');

foreach ($terminalRows as $row) {
    $productSlugs = array_values(array_filter(array_map('trim', explode(';', (string)$row['product_slug']))));
    if ($productSlugs === []) fail('Documento terminale senza product_slug: ' . $row['target_filename']);
    foreach ($productSlugs as $slug) {
        if (!array_key_exists($slug, $productMap)) {
            fail('product_slug non presente nel registry: ' . $slug);
        }
    }

    $modelCode = trim((string)$row['model_code']);
    if ($modelCode !== '') {
        if (count($productSlugs) !== 1) fail('model_code con più product_slug: ' . $row['target_filename']);
        if (!isset($productMap[$productSlugs[0]][$modelCode])) {
            fail('model_code non presente nel prodotto ' . $productSlugs[0] . ': ' . $modelCode);
        }
    }
}

echo "OK supplemental import: 21 prodotti, 38 modelli, 57 PDF (55 terminali).\n";
