<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$registryPath = $root . '/database/import/historical_products_registry.json';
$manifestPath = $root . '/database/import/document_migration_manifest.csv';

$raw = file_get_contents($registryPath);
if ($raw === false) throw new RuntimeException('Registro storico non leggibile.');
$data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);

$categorySlugs = [];
$productSlugs = [];
$modelCodes = [];
$counts = ['categories'=>0,'products'=>0,'models'=>0];

$walk = function(array $category) use (&$walk,&$categorySlugs,&$productSlugs,&$modelCodes,&$counts): void {
    foreach (['name','slug'] as $required) {
        if (trim((string)($category[$required] ?? '')) === '') throw new RuntimeException('Categoria senza ' . $required);
    }
    $slug = (string)$category['slug'];
    if (isset($categorySlugs[$slug])) throw new RuntimeException('Slug categoria duplicato: ' . $slug);
    $categorySlugs[$slug] = true;
    $counts['categories']++;

    foreach ($category['products'] ?? [] as $product) {
        foreach (['name','slug'] as $required) {
            if (trim((string)($product[$required] ?? '')) === '') throw new RuntimeException('Prodotto senza ' . $required . ' in ' . $slug);
        }
        $pSlug = (string)$product['slug'];
        if (isset($productSlugs[$pSlug])) throw new RuntimeException('Slug prodotto duplicato: ' . $pSlug);
        $productSlugs[$pSlug] = true;
        $counts['products']++;
        foreach ($product['models'] ?? [] as $model) {
            $normalized = mb_strtolower(trim((string)$model));
            if ($normalized === '') throw new RuntimeException('Codice modello vuoto in ' . $pSlug);
            if (isset($modelCodes[$normalized])) throw new RuntimeException('Codice modello duplicato nel registro storico: ' . $model);
            $modelCodes[$normalized] = true;
            $counts['models']++;
        }
    }
    foreach ($category['children'] ?? [] as $child) $walk($child);
};

foreach ($data['categories'] ?? [] as $category) $walk($category);
if ($counts['categories'] < 1 || $counts['products'] < 1) throw new RuntimeException('Registro storico vuoto.');

$fh = fopen($manifestPath, 'rb');
if (!$fh) throw new RuntimeException('Manifest documenti non leggibile.');
$header = fgetcsv($fh);
$requiredColumns = ['source_url','title','type_slug','group_label','target_filename','category_slug','notes'];
if ($header !== $requiredColumns) throw new RuntimeException('Header manifest documenti inatteso.');
$urls = [];
$files = [];
$documentCount = 0;
while (($row = fgetcsv($fh)) !== false) {
    if (count($row) !== count($header)) throw new RuntimeException('Riga CSV non valida.');
    $item = array_combine($header, $row);
    if (!preg_match('#^https://www\.idemaclima\.it/wp-content/uploads/#i', (string)$item['source_url'])) throw new RuntimeException('URL sorgente non consentito.');
    if (!preg_match('/\.pdf$/i', (string)$item['target_filename'])) throw new RuntimeException('Target non PDF: ' . $item['target_filename']);
    if (isset($urls[$item['source_url']])) throw new RuntimeException('URL documento duplicato.');
    if (isset($files[$item['target_filename']])) throw new RuntimeException('Filename documento duplicato.');
    $urls[$item['source_url']] = true;
    $files[$item['target_filename']] = true;
    $documentCount++;
}
fclose($fh);
if ($documentCount < 1) throw new RuntimeException('Manifest documenti vuoto.');

echo "Historical import smoke OK\n";
echo "Categorie: {$counts['categories']}\nProdotti: {$counts['products']}\nModelli: {$counts['models']}\nDocumenti iniziali: {$documentCount}\n";
