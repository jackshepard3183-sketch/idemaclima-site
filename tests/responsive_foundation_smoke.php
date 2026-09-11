<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$public=file_get_contents($root.'/app/Views/public/_layout_typography.php');
$admin=file_get_contents($root.'/app/Views/admin/_layout_start.php');
$family=file_get_contents($root.'/app/Views/public/technical_sheets/family.php');
foreach([$public,$admin,$family] as $content)if(!is_string($content)||$content==='')throw new RuntimeException('CSS responsive non leggibile.');
$checks=[
 [$public,'text-wrap:balance','titoli bilanciati'],
 [$public,'text-wrap:pretty','paragrafi impaginati'],
 [$public,'font-size:16px','controlli mobile senza zoom'],
 [$public,'width:calc(100% - 24px)','margini smartphone'],
 [$admin,'body.nav-open{overflow:hidden}','drawer senza scorrimento pagina'],
 [$admin,'-webkit-overflow-scrolling:touch','tabelle touch'],
 [$admin,'.toolbar .btnlink,.toolbar>.btn','azioni toolbar responsive'],
 [$family,'white-space:nowrap','nomi modello uniti'],
];
foreach($checks as [$haystack,$needle,$label])if(!str_contains($haystack,$needle))throw new RuntimeException('Check responsive fallito: '.$label);
fwrite(STDOUT,"Responsive foundation smoke OK\n");
