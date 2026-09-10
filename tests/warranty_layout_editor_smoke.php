<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$controller=(string)file_get_contents($root.'/app/Controllers/Admin/WarrantyCertificateLayoutTrait.php');
$pdf=(string)file_get_contents($root.'/app/Controllers/Admin/WarrantyCertificatePdfTrait.php');
$view=(string)file_get_contents($root.'/app/Views/admin/warranty_certificate_layout.php');
$routes=(string)file_get_contents($root.'/public/index.php');
$migration=(string)file_get_contents($root.'/database/migrations/029_warranty_certificate_layout.sql');
foreach([$controller,$pdf,$view,$routes,$migration] as $content)if($content==='')throw new RuntimeException('File editor PDF non leggibile.');
$checks=[
    [$controller,'array_intersect_key($saved,$defaults)','chiavi configurazione limitate'],
    [$controller,"preg_match('/^#[0-9a-f]{6}$/',$value)",'validazione colori'],
    [$controller,"max(.85,min(1.10",'limiti tipografici'],
    [$controller,"JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR",'salvataggio JSON sicuro'],
    [$controller,"AdminAuth::requireLogin()",'anteprima autenticata'],
    [$pdf,'$layout=self::certificateLayout()','layout collegato al PDF'],
    [$pdf,"foreach(\$layout['order'] as \$block)",'ordine blocchi applicato'],
    [$view,'name="block_order"','ordine editor'],
    [$view,'Ripristina modello predefinito','ripristino modello'],
    [$view,'Security::nonce()','nonce CSP'],
    [$routes,"/admin/warranties/certificate-layout/preview",'rotta anteprima'],
    [$migration,'warranty_certificate_layouts','tabella layout'],
];
foreach($checks as [$haystack,$needle,$label])if(!str_contains($haystack,$needle))throw new RuntimeException('Check editor PDF fallito: '.$label);
fwrite(STDOUT,"Warranty layout editor smoke OK\n");
