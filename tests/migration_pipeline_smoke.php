<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$historical=$root.'/database/import/preflight_historical_products.php';
$documents=$root.'/database/import/import_downloaded_documents.php';
$migration=$root.'/database/migrations/017_product_secondary_categories.sql';

foreach ([$historical,$documents,$migration] as $path) {
    if (!is_file($path)) throw new RuntimeException('File pipeline mancante: '.basename($path));
}

$historicalCode=(string)file_get_contents($historical);
$documentCode=(string)file_get_contents($documents);
$migrationCode=(string)file_get_contents($migration);

$requirements=[
    [$historicalCode,'model_conflicts','preflight prodotti deve verificare conflitti modello'],
    [$historicalCode,'missing_secondary_categories','preflight prodotti deve verificare categorie secondarie'],
    [$historicalCode,'preflight-read-only','preflight prodotti deve dichiararsi read-only'],
    [$documentCode,'beginTransaction','import documenti deve aprire una transazione'],
    [$documentCode,'rollBack','import documenti deve prevedere rollback'],
    [$documentCode,'migrate_documents.php --download','import DB deve richiedere prima la copia locale'],
    [$documentCode,"hash_file('sha256'",'import DB deve ricalcolare SHA-256'],
    [$migrationCode,'product_category_links','migration categorie multiple mancante'],
    [$migrationCode,'UNIQUE KEY uq_product_category','vincolo univoco relazione categoria prodotto mancante'],
];
foreach ($requirements as [$haystack,$needle,$message]) {
    if (!str_contains($haystack,$needle)) throw new RuntimeException($message);
}

if (str_contains($historicalCode,'INSERT INTO products') || str_contains($historicalCode,'UPDATE products')) {
    throw new RuntimeException('Il preflight prodotti non deve contenere scritture sui prodotti.');
}

echo "Migration pipeline smoke OK\n";
