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

$samples=[
    '10-anni'=>[
        'registration'=>[
            'warranty_years'=>10,'extension_formula'=>'5 + 5',
            'customer_first_name'=>'Alessandro Massimiliano','customer_last_name'=>'De Santis-Rappresentanze Termotecniche',
            'fiscal_code'=>'DSSLSN80A01F205X','address'=>'Via della Progettazione Termotecnica e degli Impianti ad Alta Efficienza, 125',
            'city'=>'San Giorgio delle Pertiche','province'=>'PD','postal_code'=>'35010','region'=>'Friuli-Venezia Giulia',
            'email'=>'amministrazione.garanzie@example.com','phone'=>'+39 031 888 1637',
            'product_type'=>'multi','outer_unit'=>'3MWTZ-70-R32',
            'combination'=>'WTMC-25UI-BLK-R32 + 2x WTMC-35UI-BLK-R32','code'=>'WTMC-35UI-BLK-R32','invoice_date'=>'2026-08-31',
        ],
        'units'=>[
            ['unit_type'=>'outdoor','serial_number'=>'UE-TEST-2026-000000000001'],
            ['unit_type'=>'indoor','serial_number'=>'UI-TEST-2026-000000000001'],
            ['unit_type'=>'indoor','serial_number'=>'UI-TEST-2026-000000000002'],
            ['unit_type'=>'indoor','serial_number'=>'UI-TEST-2026-000000000003'],
        ],
    ],
    '5-anni'=>[
        'registration'=>[
            'warranty_years'=>5,'extension_formula'=>'2 + 3',
            'customer_first_name'=>'Alessandro','customer_last_name'=>'De Santis',
            'fiscal_code'=>'DSSLSN80A01F205X','address'=>'Via della Progettazione Termotecnica, 125',
            'city'=>'Vertemate con Minoprio','province'=>'CO','postal_code'=>'22070','region'=>'Lombardia',
            'email'=>'cliente@example.com','phone'=>'+39 031 888 1637',
            'product_type'=>'mono','outer_unit'=>'','combination'=>'ISPT-12-R32','code'=>'ISPT-12-R32','invoice_date'=>'2026-09-11',
        ],
        'units'=>[
            ['unit_type'=>'outdoor','serial_number'=>'UE-TEST-5ANNI-000001'],
            ['unit_type'=>'indoor','serial_number'=>'UI-TEST-5ANNI-000001'],
        ],
    ],
];
foreach($samples as $key=>$sample){
    $pdf=WarrantyPdfFixture::render($sample['registration'],$sample['units'],'IDM-TEST2026');
    if(!str_starts_with($pdf,'%PDF-1.4')||!str_ends_with($pdf,"%%EOF")||strlen($pdf)<2000)throw new RuntimeException('Certificato PDF di collaudo non valido: '.$key);
    $target='/tmp/idemaclima-warranty-certificate-'.$key.'.pdf';
    if(file_put_contents($target,$pdf,LOCK_EX)===false)throw new RuntimeException('Impossibile salvare il certificato PDF di collaudo: '.$key);
}
fwrite(STDOUT,"Warranty certificate fixtures OK\n");
