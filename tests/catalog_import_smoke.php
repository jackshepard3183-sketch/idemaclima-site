<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$registry = $root . '/database/import/catalogs_registry.csv';
$inventoryPath = $root . '/database/import/reports/document-archive-inventory-2026-08-26.json';
$migration = file_get_contents($root . '/database/migrations/020_catalog_document_links.sql');
$importer = file_get_contents($root . '/database/import/import_catalogs.php');
$admin = file_get_contents($root . '/app/Controllers/Admin/CatalogsController.php');
$publicView = file_get_contents($root . '/app/Views/public/content/catalogs.php');
$adminView = file_get_contents($root . '/app/Views/admin/content_catalogs.php');
$routes = file_get_contents($root . '/public/index.php');

foreach ([$migration,$importer,$admin,$adminView,$routes,$publicView] as $content) {
    if (!is_string($content) || $content === '') throw new RuntimeException('File cataloghi non leggibile.');
}

$fh = fopen($registry, 'rb');
if (!$fh) throw new RuntimeException('Registry cataloghi non leggibile.');
$header = fgetcsv($fh);
if (!$header) throw new RuntimeException('Header registry cataloghi mancante.');
$rows = [];
while (($csv = fgetcsv($fh)) !== false) {
    if (count($csv) !== count($header)) throw new RuntimeException('Riga registry cataloghi non valida.');
    $rows[] = array_combine($header, $csv);
}
fclose($fh);

if (count($rows) !== 15) throw new RuntimeException('Attesi 15 cataloghi pubblici, trovati ' . count($rows) . '.');
$slugs = [];
$files = [];
foreach ($rows as $row) {
    $slug = strtolower(trim((string)$row['slug']));
    $file = strtolower(trim((string)$row['source_filename']));
    if ($slug === '' || isset($slugs[$slug])) throw new RuntimeException('Slug catalogo vuoto o duplicato: ' . $slug);
    if (!str_ends_with($file, '.pdf') || isset($files[$file])) throw new RuntimeException('PDF catalogo non valido o duplicato: ' . $file);
    $slugs[$slug] = true;
    $files[$file] = true;
}

$inventory = json_decode((string)file_get_contents($inventoryPath), true, flags: JSON_THROW_ON_ERROR);
$catalogFiles = array_map('strtolower', $inventory['groups']['catalogs'] ?? []);
foreach (array_keys($files) as $file) {
    if (!in_array($file, $catalogFiles, true)) throw new RuntimeException('PDF catalogo non presente nel censimento storico: ' . $file);
}

$v8 = array_values(array_filter($rows, static fn(array $r): bool => $r['slug'] === 'catalogo-vrf-v8-2026'));
if (count($v8) !== 1 || $v8[0]['source_filename'] !== 'C-CATALOGO-CAC-V8-2026.pdf' || $v8[0]['document_year'] !== '2026') {
    throw new RuntimeException('Catalogo VRF V8 2026 non normalizzato correttamente.');
}

$checks = [
    [$migration, 'ADD COLUMN document_id', 'document_id cataloghi'],
    [$migration, 'CHECK (document_id IS NOT NULL OR pdf_path IS NOT NULL)', 'target catalogo obbligatorio'],
    [$importer, '--preflight', 'preflight cataloghi'],
    [$importer, 'document_source_aliases', 'risoluzione alias documenti'],
    [$importer, 'beginTransaction()', 'import transazionale'],
    [$admin, 'Documento canonico non valido o non pubblicato', 'validazione documento admin'],
    [$admin, 'Audit::log', 'audit cataloghi'],
    [$admin, 'catalog.duplicate', 'audit duplica catalogo'],
    [$adminView, 'Duplica', 'UI duplica catalogo'],
    [$routes, '/admin/content/catalogs/duplicate', 'route duplica catalogo'],
    [$publicView, "/documento/", 'download canonico pubblico'],
];
foreach ($checks as [$haystack,$needle,$label]) {
    if (!str_contains($haystack, $needle)) throw new RuntimeException('Check cataloghi fallito: ' . $label);
}

fwrite(STDOUT, "Catalog import smoke OK: 15 cataloghi, tutti censiti nell'archivio storico.\n");
