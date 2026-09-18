<?php
declare(strict_types=1);
$view=(string)file_get_contents(dirname(__DIR__).'/app/Views/admin/warranty_registrations.php');
$checks=[
 ["'invoice_date' => 'Data fattura'",'ordinamento data fattura'],
 ["'received' => 'Data ricevuta'",'ordinamento data ricevuta'],
 ['usort($filteredRegistrations','ordinamento server-side'],
 ["date('d-m-Y'",'formato data italiano'],
 ['warranty-date{white-space:nowrap','date su una riga'],
 ['aria-sort','accessibilità ordinamento'],
 ['sort-indicator','indicatore ordine'],
];
foreach($checks as [$needle,$label])if(!str_contains($view,$needle))throw new RuntimeException('Controllo elenco garanzie assente: '.$label);
echo "Warranty list sort smoke OK\n";
