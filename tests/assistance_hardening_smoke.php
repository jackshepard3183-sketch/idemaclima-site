<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$schema = file_get_contents($root . '/database/migrations/013_assistance_resources.sql');
$admin = file_get_contents($root . '/app/Controllers/Admin/AssistanceController.php');
$public = file_get_contents($root . '/app/Controllers/Public/AssistanceController.php');
$routes = file_get_contents($root . '/public/index.php');

foreach ([
    'schema' => $schema,
    'admin' => $admin,
    'public' => $public,
    'routes' => $routes,
] as $name => $content) {
    if (!is_string($content) || $content === '') {
        throw new RuntimeException('File non leggibile: ' . $name);
    }
}

$mustSchema = [
    'chk_assistance_resource_target',
    'document_id IS NOT NULL AND external_url IS NULL',
    'document_id IS NULL AND external_url IS NOT NULL',
    'ON DELETE RESTRICT',
    'archived_at TIMESTAMP NULL',
];
foreach ($mustSchema as $needle) {
    if (!str_contains($schema, $needle)) throw new RuntimeException('Schema Assistenza incompleto: ' . $needle);
}

$mustAdmin = [
    "in_array(\$scheme, ['http','https'], true)",
    "(\$documentId === null) === (\$external === null)",
    "Audit::log('assistance_resource.archive'",
    'archived_at=CURRENT_TIMESTAMP',
    'Risorsa Assistenza non trovata.',
];
foreach ($mustAdmin as $needle) {
    if (!str_contains($admin, $needle)) throw new RuntimeException('Controller admin Assistenza incompleto: ' . $needle);
}

if (!str_contains($public, 'ASSISTANCE_FORM_URL')) throw new RuntimeException('URL form assistenza non configurabile.');
if (!str_contains($public, 'ar.archived_at IS NULL')) throw new RuntimeException('Filtro archivio pubblico assente.');
if (!str_contains($routes, "post('/admin/assistance/archive'")) throw new RuntimeException('Route archivio Assistenza assente.');

echo "Assistance hardening smoke OK\n";
