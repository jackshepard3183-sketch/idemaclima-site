<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$downloader=file_get_contents($root.'/database/import/migrate_documents.php');
$dbImporter=file_get_contents($root.'/database/import/import_downloaded_documents.php');
$preflight=file_get_contents($root.'/database/import/preflight_historical_products.php');
$readiness=file_get_contents($root.'/scripts/staging_readiness.php');
$migrate=file_get_contents($root.'/scripts/migrate.php');
$stagingPreflight=file_get_contents($root.'/scripts/staging_preflight.sh');
$bootstrap=file_get_contents($root.'/scripts/bootstrap.php');
$appConfig=file_get_contents($root.'/config/app.php');
$pipeline=file_get_contents($root.'/database/import/SAFETY_PIPELINE.md');
$warrantyMigration=file_get_contents($root.'/database/migrations/018_warranty_hardening.sql');
$workflow=file_get_contents($root.'/.github/workflows/deploy-staging-aruba.yml');
foreach([$downloader,$dbImporter,$preflight,$readiness,$migrate,$stagingPreflight,$bootstrap,$appConfig,$pipeline,$warrantyMigration,$workflow] as $content){if(!is_string($content)||$content==='')throw new RuntimeException('File staging pipeline non leggibile.');}
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
    [$readiness,"'APP_TIMEZONE'",'timezone readiness'],
    [$readiness,"'Compatibilità database migration'",'DB version compatibility'],
    [$migrate,"in_array('--status'",'migration status mode'],
    [$migrate,'information_schema.tables','migration status read only'],
    [$migrate,'GET_LOCK','migration lock'],
    [$migrate,'checksum','migration checksum'],
    [$migrate,'IDEMA_INTERNAL_MIGRATION_RUN','migration web solo da wrapper interno'],
    [$stagingPreflight,'php scripts/migrate.php --status','one command migration status'],
    [$stagingPreflight,'bash tests/run_all.sh','one command test suite'],
    [$bootstrap,'date_default_timezone_set','bootstrap timezone'],
    [$appConfig,"'timezone'",'timezone configuration'],
    [$pipeline,'migrate_documents.php --execute` è disabilitato','documentazione execute disabilitato'],
    [$pipeline,'php scripts/migrate.php --execute','documentazione migration runner'],
    [$workflow,'MIGRATION_TOKEN="$(openssl rand -hex 32)"','token migration casuale'],
    [$workflow,'MIGRATION_FILE="idemaclima-migrate-','wrapper eseguibile da Apache'],
    [$workflow,'cleanup_migration_wrapper','rimozione wrapper migration'],
    [$workflow,"grep -q 'Completato. Migration applicate:'",'verifica esecuzione migration'],
    [$workflow,'verify_deploy_integrity','verifica integrita con retry'],
    [$workflow,'Integrity mismatch after retries','errore file remoto identificabile'],
];
foreach($checks as [$haystack,$needle,$label]){if(!str_contains($haystack,$needle))throw new RuntimeException('Check staging pipeline fallito: '.$label);}
if(str_contains($warrantyMigration,'idx_warranty_registrations_certificate'))throw new RuntimeException('Indice warranty certificate ridondante ancora presente.');
fwrite(STDOUT,"Staging pipeline hardening smoke OK\n");
