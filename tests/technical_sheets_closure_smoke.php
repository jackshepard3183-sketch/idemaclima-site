<?php

declare(strict_types=1);

$root=dirname(__DIR__);$family=file_get_contents($root.'/app/Views/public/technical_sheets/family.php');$product=file_get_contents($root.'/app/Views/public/technical_sheets/product.php');$controller=file_get_contents($root.'/app/Controllers/Public/TechnicalSheetsController.php');$search=file_get_contents($root.'/app/Views/public/technical_sheets/search.php');$index=file_get_contents($root.'/app/Views/public/technical_sheets/index.php');
foreach([$family,$product,$controller,$search,$index] as $content)if(!is_string($content)||$content==='')throw new RuntimeException('File Schede tecniche non leggibile.');
foreach(['ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR'] as $name)if(!str_contains($controller,"'".$name."'"))throw new RuntimeException('Mono Split dedicato mancante: '.$name);
$checks=[
 [$family,'class="product-item dedicated-product"','link diretto per i sei Mono Split'],
 [$family,'<?php else:?><details class="product-item"','accordion per gli altri prodotti'],
 [$family,'models-section','elenco modelli negli accordion'],
 [$family,'group-schede-tecniche','badge Schede tecniche verde'],
 [$family,'group-tabelle-rese','badge Tabelle rese verde'],
 [$family,'group-detrazioni-fiscali','badge Detrazioni azzurro'],
 [$family,'group-conto-termico','badge Conto termico azzurro'],
 [$family,'group-manuali','badge Manuali neutro'],
 [$controller,"['Schede tecniche','Tabelle rese','Detrazioni fiscali','Conto termico','Manuali']",'ordine documenti server-side'],
 [$controller,"stripos($name,'tabella')!==false=>'Tabelle rese'",'normalizzazione Tabella rese singolare'],
 [$controller,'if (!self::hasDedicatedPage($product))','redirect prodotti non dedicati'],
 [$search,"\$dedicated?'Apri pagina':'Apri accordion'",'risultati ricerca coerenti'],
 [$product,'group-tabelle-rese','colore Tabelle rese Mono Split'],
];
foreach($checks as [$haystack,$needle,$label])if(!str_contains($haystack,$needle))throw new RuntimeException('Check Schede tecniche fallito: '.$label);
foreach(['product-page-link','inline-description'] as $forbidden)if(str_contains($family,'class="'.$forbidden.'"'))throw new RuntimeException('Elemento non consentito negli accordion: '.$forbidden);
if(str_contains($index,"['Terminali idronici','PdC Monoblocco'"))throw new RuntimeException('La serie vuota Terminali idronici non deve comparire nei badge pubblici.');
fwrite(STDOUT,"Technical sheets closure smoke OK\n");
