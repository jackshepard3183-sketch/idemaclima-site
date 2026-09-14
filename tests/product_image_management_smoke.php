<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$controller=file_get_contents($root.'/app/Controllers/Admin/ProductImagesController.php');$view=file_get_contents($root.'/app/Views/admin/product_images.php');$migration=file_get_contents($root.'/database/migrations/058_product_image_review_workflow.sql');$router=file_get_contents($root.'/public/index.php');$layout=file_get_contents($root.'/app/Views/admin/_layout_start.php');
foreach([$controller,$view,$migration,$router,$layout] as $content)if(!is_string($content)||$content==='')throw new RuntimeException('Gestione immagini prodotti incompleta.');
foreach(['ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR'] as $name)if(!str_contains($controller,$name)||!str_contains($migration,$name))throw new RuntimeException('Protezione Mono Split mancante: '.$name);
foreach(['to_review','insufficient','recovered','missing_catalog','approved'] as $status)if(!str_contains($migration,$status)||!str_contains($view,$status))throw new RuntimeException('Stato censimento mancante: '.$status);
foreach(['/admin/product-images','ProductImagesController','Approva e assegna','Media Library'] as $needle)if(!str_contains($router.$layout.$view,$needle))throw new RuntimeException('Workflow immagini mancante: '.$needle);
$verified=file_get_contents($root.'/database/migrations/059_seed_verified_catalog_image_candidates.sql');
if(!is_string($verified)||$verified==='')throw new RuntimeException('Candidate catalogo verificate mancanti.');
foreach(['ICZ-R32','ITXI-R32','IMIHQ4CN18','ISZ(Z)-R32'] as $name)if(!str_contains($verified,$name))throw new RuntimeException('Candidata verificata mancante: '.$name);
$verifiedMore=file_get_contents($root.'/database/migrations/060_seed_more_verified_catalog_image_candidates.sql');
if(!is_string($verifiedMore)||$verifiedMore==='')throw new RuntimeException('Secondo gruppo di candidate catalogo mancante.');
foreach(['ITZ-R32','IQZZI-R32','IMI2-Q4CDN1','IFZI-R32','IDV-V100WDN1(D)'] as $name)if(!str_contains($verifiedMore,$name))throw new RuntimeException('Candidata verificata mancante: '.$name);
$pathFix=file_get_contents($root.'/database/migrations/061_fix_catalog_candidate_public_paths.sql');
if(!is_string($pathFix)||!str_contains($pathFix,"/assets/product-images/"))throw new RuntimeException('Correzione percorso pubblico candidate mancante.');
fwrite(STDOUT,"Product image management smoke OK\n");
