<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$view=(string)file_get_contents($root.'/app/Views/admin/warranty_import.php');
$controller=(string)file_get_contents($root.'/app/Controllers/Admin/WarrantyImportController.php');
$checks=[
 [$view,'multiple required','selezione multipla'],
 [$view,'localeCompare','ordinamento naturale'],
 [$view,'fetch(\'/idemaclima/admin/warranties/import/run\'','invio sequenziale'],
 [$view,'Riprova dal file bloccato','ripresa dopo errore'],
 [$view,'beforeunload','protezione durante importazione'],
 [$view,'data.append(\'manifest\'','un file per richiesta'],
 [$controller,'source_wpforms_id=?','deduplicazione WPForms'],
];
foreach($checks as [$haystack,$needle,$label])if(!str_contains($haystack,$needle))throw new RuntimeException('Controllo coda importazione assente: '.$label);
echo "Warranty import queue smoke OK\n";
