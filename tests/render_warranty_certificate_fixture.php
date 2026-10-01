<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/Controllers/Admin/WarrantyCertificatePdfTrait.php';
require dirname(__DIR__) . '/app/Controllers/Admin/WarrantyCertificateLayoutTrait.php';

final class WarrantyPdfFixture
{
    use \App\Controllers\Admin\WarrantyCertificatePdfTrait;
    use \App\Controllers\Admin\WarrantyCertificateLayoutTrait;

    public static function render(array $registration,array $units,string $number):string
    {
        return self::buildCertificatePdf($registration,$units,$number);
    }
}

$registration=[
    'warranty_years'=>10,
    'extension_formula'=>'5 + 5',
    'customer_first_name'=>'Alessandro Massimiliano',
    'customer_last_name'=>'De Santis-Rappresentanze Termotecniche',
    'fiscal_code'=>'DSSLSN80A01F205X',
    'address'=>'Via della Progettazione Termotecnica e degli Impianti ad Alta Efficienza, 125',
    'city'=>'San Giorgio delle Pertiche',
    'province'=>'PD',
    'postal_code'=>'35010',
    'region'=>'Friuli-Venezia Giulia',
    'email'=>'amministrazione.garanzie@example.com',
    'phone'=>'+39 031 888 1637',
    'product_type'=>'multi',
    'outer_unit'=>'3MWTZ-70-R32',
    'combination'=>'WTMC-25UI-BLK-R32 + 2x WTMC-35UI-BLK-R32',
    'code'=>'WTMC-35UI-BLK-R32',
    'invoice_date'=>'2026-08-31',
];
$units=[
    ['unit_type'=>'outdoor','serial_number'=>'UE-TEST-2026-000000000001'],
    ['unit_type'=>'indoor','serial_number'=>'UI-TEST-2026-000000000001'],
    ['unit_type'=>'indoor','serial_number'=>'UI-TEST-2026-000000000002'],
    ['unit_type'=>'indoor','serial_number'=>'UI-TEST-2026-000000000003'],
];
$pdf=WarrantyPdfFixture::render($registration,$units,'IDM-TEST2026');
if(!str_starts_with($pdf,'%PDF-1.4')||!str_ends_with($pdf,"%%EOF")||strlen($pdf)<2000)throw new RuntimeException('Certificato PDF di collaudo non valido.');
$target='/tmp/idemaclima-warranty-certificate-sample.pdf';
if(file_put_contents($target,$pdf,LOCK_EX)===false)throw new RuntimeException('Impossibile salvare il certificato PDF di collaudo.');
fwrite(STDOUT,"Warranty certificate fixture OK\n");
