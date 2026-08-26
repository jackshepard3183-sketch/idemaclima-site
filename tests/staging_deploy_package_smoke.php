<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$builder=file_get_contents($root.'/scripts/build_staging_package.sh');
$env=file_get_contents($root.'/deploy/.env.staging.example');
$guide=file_get_contents($root.'/deploy/DEPLOY_STAGING.md');
foreach([$builder,$env,$guide] as $content){if(!is_string($content)||$content==='')throw new RuntimeException('File deploy staging non leggibile.');}
$checks=[
    [$builder,"--exclude='.env'",'esclusione .env'],
    [$builder,'DEPLOY_SHA256SUMS.txt','manifest SHA-256'],
    [$builder,"--exclude='storage/private/*'",'esclusione dati privati'],
    [$builder,"--exclude='public/uploads/documents/migrated/*'",'esclusione PDF migrati'],
    [$env,'APP_URL=https://www.rappresentanzeguanzirolisas.it/idemaclima','APP_URL staging'],
    [$env,'APP_BASE_PATH=/idemaclima','base path staging'],
    [$env,'APP_TIMEZONE=Europe/Rome','timezone staging'],
    [$guide,'php scripts/migrate.php --status','status migration'],
    [$guide,'X-Robots-Tag: noindex, nofollow, noarchive','noindex staging'],
];
foreach($checks as [$haystack,$needle,$label]){if(!str_contains($haystack,$needle))throw new RuntimeException('Check deploy staging fallito: '.$label);}
fwrite(STDOUT,"Staging deploy package smoke OK\n");
