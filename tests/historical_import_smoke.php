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
$secondaryLinks = [];
$counts = ['categories'=>0,'products'=>0,'models'=>0,'secondary_category_links'=>0];

$walk = function(array $category) use (&$walk,&$categorySlugs,&$productSlugs,&$modelCodes,&$secondaryLinks,&$counts): void {
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

        $seenSecondary = [];
        foreach ($product['also_category_slugs'] ?? [] as $secondarySlug) {
            $secondarySlug = trim((string)$secondarySlug);
            if ($secondarySlug === '') throw new RuntimeException('Categoria secondaria vuota in ' . $pSlug);
            if ($secondarySlug === $slug) throw new RuntimeException('Categoria secondaria uguale alla primaria in ' . $pSlug);
            if (isset($seenSecondary[$secondarySlug])) throw new RuntimeException('Categoria secondaria duplicata in ' . $pSlug . ': ' . $secondarySlug);
            $seenSecondary[$secondarySlug] = true;
            $secondaryLinks[] = ['product_slug'=>$pSlug,'category_slug'=>$secondarySlug];
            $counts['secondary_category_links']++;
        }
    }
    foreach ($category['children'] ?? [] as $child) $walk($child);
};

foreach ($data['categories'] ?? [] as $category) $walk($category);
if ($counts['categories'] < 1 || $counts['products'] < 1) throw new RuntimeException('Registro storico vuoto.');

foreach ($secondaryLinks as $link) {
    if (!isset($categorySlugs[$link['category_slug']])) {
        throw new RuntimeException('Categoria secondaria inesistente per ' . $link['product_slug'] . ': ' . $link['category_slug']);
    }
}

// Casi noti di prodotti realmente condivisi fra Multi Split e Commerciale.
$expectedShared = [
    'legacy-iqkei-ui' => 'linea-commerciale-r410a-unita-interne',
    'legacy-ifkei-ui' => 'linea-commerciale-r410a-unita-interne',
    'legacy-itkei-ui' => 'linea-commerciale-r410a-unita-interne',
];
foreach ($expectedShared as $productSlug => $categorySlug) {
    $found = false;
    foreach ($secondaryLinks as $link) {
        if ($link['product_slug'] === $productSlug && $link['category_slug'] === $categorySlug) {
            $found = true;
            break;
        }
    }
    if (!$found) throw new RuntimeException('Relazione multi-linea attesa assente: ' . $productSlug . ' -> ' . $categorySlug);
}

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
    if (!preg_match('#^https://www\\.idemaclima\\.it/wp-content/uploads/#i', (string)$item['source_url'])) throw new RuntimeException('URL sorgente non consentito.');
    if (!preg_match('/\\.pdf$/i', (string)$item['target_filename'])) throw new RuntimeException('Target non PDF: ' . $item['target_filename']);
    if (isset($urls[$item['source_url']])) throw new RuntimeException('URL documento duplicato.');
    if (isset($files[$item['target_filename']])) throw new RuntimeException('Filename documento duplicato.');
    $urls[$item['source_url']] = true;
    $files[$item['target_filename']] = true;
    $documentCount++;
}
fclose($fh);
if ($documentCount < 1) throw new RuntimeException('Manifest documenti vuoto.');

echo "Historical import smoke OK\n";
echo "Categorie: {$counts['categories']}\n";
echo "Prodotti: {$counts['products']}\n";
echo "Modelli: {$counts['models']}\n";
echo "Relazioni categorie secondarie: {$counts['secondary_category_links']}\n";
echo "Documenti iniziali: {$documentCount}\n";
