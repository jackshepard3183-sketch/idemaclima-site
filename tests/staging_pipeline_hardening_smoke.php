<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$downloader=file_get_contents($root.'/database/import/migrate_documents.php');
$dbImporter=file_get_contents($root.'/database/import/import_downloaded_documents.php');
$preflight=file_get_contents($root.'/database/import/preflight_historical_products.php');
$readiness=file_get_contents($root.'/scripts/staging_readiness.php');
$pipeline=file_get_contents($root.'/database/import/SAFETY_PIPELINE.md');
$warrantyMigration=file_get_contents($root.'/database/migrations/018_warranty_hardening.sql');
foreach([$downloader,$dbImporter,$preflight,$readiness,$pipeline,$warrantyMigration] as $content){if(!is_string($content)||$content==='')throw new RuntimeException('File staging pipeline non leggibile.');}
$checks=[
    [$downloader,'Modalita --execute disabilitata','legacy execute disabilitato'],
    [$downloader,"'actual_filename'",'mapping filename deduplicato'],
    [$dbImporter,'document-migration-','report download usato'],
    [$dbImporter,'resolveLocalPdf','resolver file deduplicati'],
    [$dbImporter,'SHA-256 diverso dal report download','verifica hash report/filesystem'],
    [$preflight,"'product_slug_conflict'",'slug product conflict implementato'],
    [$readiness,"version_compare(PHP_VERSION,'8.2.0'",'PHP 8.2 readiness'],
    [$readiness,"'pdo_mysql'",'pdo mysql readiness'],
    [$readiness,"'APP_KEY'",'APP_KEY readiness'],
    [$readiness,"'Connessione database'",'DB readiness'],
    [$pipeline,'migrate_documents.php --execute` è disabilitato','documentazione execute disabilitato'],
];
foreach($checks as [$haystack,$needle,$label]){if(!str_contains($haystack,$needle))throw new RuntimeException('Check staging pipeline fallito: '.$label);}
if(str_contains($warrantyMigration,'idx_warranty_registrations_certificate'))throw new RuntimeException('Indice warranty certificate ridondante ancora presente.');
fwrite(STDOUT,"Staging pipeline hardening smoke OK\n");
