<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/app/Controllers/Admin/ContentController.php');
$public = file_get_contents($root . '/app/Controllers/Public/ContentController.php');
$album = file_get_contents($root . '/app/Views/admin/content_album_form.php');
$reference = file_get_contents($root . '/app/Views/admin/content_reference_form.php');
$referenceList = file_get_contents($root . '/app/Views/admin/content_references.php');
$upload = file_get_contents($root . '/app/Core/Upload.php');
$productsView = file_get_contents($root . '/app/Views/admin/products.php');
$routes = file_get_contents($root . '/public/index.php');
$migration = file_get_contents($root . '/database/migrations/021_visual_content_hardening.sql');

foreach ([$admin,$public,$album,$reference,$referenceList,$upload,$productsView,$routes,$migration] as $content) {
    if (!is_string($content) || $content === '') throw new RuntimeException('File contenuti visuali non leggibile.');
}

$checks = [
    [$admin, 'gallery.image.update', 'audit modifica immagine galleria'],
    [$admin, 'gallery.image.delete', 'audit cancellazione immagine galleria'],
    [$admin, 'reference.image.update', 'audit modifica immagine referenza'],
    [$admin, 'reference.image.delete', 'audit cancellazione immagine referenza'],
    [$admin, 'Upload::removeManaged($existing)', 'pulizia vecchia copertina'],
    [$admin, 'archived_at=NOW()', 'archiviazione contenuti'],
    [$public, 'archived_at IS NULL', 'filtro pubblico archiviati'],
    [$album, '/admin/content/gallery/image/update', 'UI modifica immagine galleria'],
    [$album, '/admin/content/gallery/image/delete', 'UI elimina immagine galleria'],
    [$reference, '/admin/content/references/image/update', 'UI modifica immagine referenza'],
    [$reference, '/admin/content/references/image/delete', 'UI elimina immagine referenza'],
    [$routes, '/admin/content/gallery/archive', 'route archivia album'],
    [$routes, '/admin/content/references/archive', 'route archivia referenza'],
    [$routes, '/admin/content/references/duplicate', 'route duplica referenza'],
    [$admin, 'reference.duplicate', 'audit duplica referenza'],
    [$referenceList, 'Duplica', 'UI duplica referenza'],
    [$upload, 'duplicateManaged', 'copia indipendente allegati'],
    [$productsView, 'product-group', 'gerarchia categorie prodotti'],
    [$productsView, 'product-series', 'gerarchia serie prodotti'],
    [$productsView, 'product-filter', 'ricerca prodotti amministrativa'],
    [$migration, 'ADD COLUMN archived_at', 'schema archiviazione'],
];

foreach ($checks as [$haystack,$needle,$label]) {
    if (!str_contains($haystack, $needle)) throw new RuntimeException('Check contenuti visuali fallito: ' . $label);
}

fwrite(STDOUT, "Visual content hardening smoke OK\n");
