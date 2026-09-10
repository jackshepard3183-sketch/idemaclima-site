<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$public = file_get_contents($root . '/app/Controllers/Public/WarrantyController.php');
$admin = file_get_contents($root . '/app/Controllers/Admin/WarrantyController.php');
$service = file_get_contents($root . '/app/Services/WarrantyService.php');
$form = file_get_contents($root . '/app/Views/public/warranty/form.php');
$migration = file_get_contents($root . '/database/migrations/018_warranty_hardening.sql');

foreach ([$public,$admin,$service,$form,$migration] as $content) {
    if (!is_string($content) || $content === '') throw new RuntimeException('File garanzia non leggibile.');
}

$checks = [
    [$public, 'company_website', 'honeypot pubblico'],
    [$public, 'La data fattura non può essere futura', 'blocco data futura'],
    [$public, 'invoice_required_snapshot', 'snapshot fattura'],
    [$public, 'fgas_required_snapshot', 'snapshot F-GAS'],
    [$public, 'min(3, $expectedIndoor)', 'limite seriali interni'],
    [$admin, 'Il modello selezionato non appartiene al prodotto indicato', 'coerenza prodotto/modello'],
    [$admin, 'warranty_certificates', 'vincolo certificato prima di issued'],
    [$admin, 'reviewed_at', 'timestamp revisione'],
    [$admin, 'Cache-Control: private, no-store', 'no-store file privati'],
    [$service, 'isIsoDate', 'validazione date ISO'],
    [$form, "L'obbligatorietà dipende dalla regola", 'fattura dinamica'],
    [$migration, 'MODIFY COLUMN invoice_file VARCHAR(500) NULL', 'fattura opzionale DB'],
];

foreach ($checks as [$haystack,$needle,$label]) {
    if (!str_contains($haystack, $needle)) throw new RuntimeException('Check garanzia fallito: ' . $label);
}

if (preg_match('/name="invoice_file"[^>]*\srequired/i', $form)) {
    throw new RuntimeException('La fattura non deve essere required staticamente nel browser.');
}

fwrite(STDOUT, "Warranty hardening smoke OK\n");
