<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

trait WarrantyCertificatePdfTrait
{
    private static function buildCertificatePdf(array $r, array $units, string $number): string
    {
        $commands = [];
        $enc = static function (string $value): string {
            $value = iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: $value;
            return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$value);
        };
        $text = static function (float $x,float $y,float $size,string $value,bool $bold=false,array $color=[0.08,0.10,0.15]) use (&$commands,$enc): void {
            $font=$bold?'F2':'F1'; $commands[]=sprintf('BT /%s %.1F Tf %.3F %.3F %.3F rg %.1F %.1F Td (%s) Tj ET',$font,$size,$color[0],$color[1],$color[2],$x,$y,$enc($value));
        };
        $textRight = static function (float $right,float $y,float $size,string $value,bool $bold=false,array $color=[0.08,0.10,0.15]) use (&$commands,$enc): void {
            $font=$bold?'F2':'F1';
            $raw=iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: $value;
            $width=strlen($raw)*$size*($bold?0.56:0.52);
            $commands[]=sprintf('BT /%s %.1F Tf %.3F %.3F %.3F rg %.1F %.1F Td (%s) Tj ET',$font,$size,$color[0],$color[1],$color[2],$right-$width,$y,$enc($value));
        };
        $textCenter = static function (float $center,float $y,float $size,string $value,bool $bold=false,array $color=[0.08,0.10,0.15]) use (&$commands,$enc): void {
            $font=$bold?'F2':'F1';
            $raw=iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: $value;
            $width=strlen($raw)*$size*($bold?0.56:0.52);
            $commands[]=sprintf('BT /%s %.1F Tf %.3F %.3F %.3F rg %.1F %.1F Td (%s) Tj ET',$font,$size,$color[0],$color[1],$color[2],$center-($width/2),$y,$enc($value));
        };
        $fitText = static function (float $x,float $right,float $y,float $maxSize,float $minSize,string $value,bool $bold=false,array $color=[0.08,0.10,0.15]) use ($text): void {
            $factor=$bold?0.56:0.52;$size=$maxSize;
            $encoded=iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: $value;
            while($size>$minSize && strlen($encoded)*$size*$factor>($right-$x))$size-=0.2;
            if(strlen($encoded)*$size*$factor>($right-$x)){
                $maxChars=max(4,(int)floor(($right-$x)/($size*$factor)));
                $value=mb_strimwidth($value,0,$maxChars-3,'...','UTF-8');
            }
            $text($x,$y,$size,$value,$bold,$color);
        };
        $rect = static function (float $x,float $y,float $w,float $h,bool $fill=false,array $color=[0.82,0.85,0.88]) use (&$commands): void {
            $commands[]=sprintf('%.3F %.3F %.3F %s %.1F %.1F %.1F %.1F re %s',$color[0],$color[1],$color[2],$fill?'rg':'RG',$x,$y,$w,$h,$fill?'f':'S');
        };
        $line = static function (float $x1,float $y1,float $x2,float $y2,array $color=[0.82,0.85,0.88]) use (&$commands): void {
            $commands[]=sprintf('%.3F %.3F %.3F RG %.1F %.1F m %.1F %.1F l S',$color[0],$color[1],$color[2],$x1,$y1,$x2,$y2);
        };
        $row = static function (float $y,string $label,string $value) use ($text,$fitText): void {
            $text(72,$y,8.2,strtoupper($label),false,[0.38,0.43,0.52]);
            $fitText(220,519,$y,10.2,7.2,mb_strtoupper($value,'UTF-8'),true);
        };
        $wrap = static function (string $value,int $width): array {
            return explode("\n",wordwrap($value,$width,"\n",true));
        };

        $flow = static function (float $left,float $right,float $y,float $size,array $segments,float $leading=12.5) use ($text): float {
            $x=$left;
            foreach ($segments as $segment) {
                $bold=(bool)($segment['bold'] ?? false);
                $words=preg_split('/\s+/',trim((string)($segment['text'] ?? ''))) ?: [];
                foreach ($words as $word) {
                    if ($word==='') continue;
                    $width=(strlen(iconv('UTF-8','Windows-1252//TRANSLIT',$word) ?: $word)+1)*$size*($bold?0.54:0.49);
                    if ($x+$width>$right && $x>$left) { $x=$left; $y-=$leading; }
                    $text($x,$y,$size,$word,$bold);
                    $x+=$width;
                }
            }
            return $y;
        };

        $green=[0.49,0.75,0.04]; $orange=[1.00,0.56,0.00]; $pale=[0.97,0.98,0.99];
        $commands[]='0.49 0.75 0.04 rg 0 836 595 6 re f';
        $logoPath=dirname(__DIR__,3).'/public/logo-idema-pdf.jpg';
        $logoData=is_file($logoPath)?file_get_contents($logoPath):false;
        if ($logoData!==false) $commands[]='q 120 0 0 80 58 719 cm /Im1 Do Q';
        else { $text(58,755,29,'IDEMA',true,[0.03,0.08,0.13]); $text(58,740,7,'QUALITY HAS A NAME',false,[0.38,0.43,0.52]); }
        $rect(459,741,78,58,true,$orange);
        $textCenter(498,766,19,(string)(int)$r['warranty_years'],true,[1,1,1]);
        $textCenter(498,750,7,'ANNI DI GARANZIA',true,[1,1,1]);
        $line(58,712,537,712);
        $text(58,674,8,'ESTENSIONE DI GARANZIA',true,$green);
        $text(58,642,22,'Certificato ufficiale',true);
        $formula=trim((string)($r['extension_formula'] ?? '')); $formulaDisplay=(string)preg_replace('/\s*\+\s*/',' + ',$formula);
        $text(58,621,11,'Copertura '.(int)$r['warranty_years'].' anni totali'.($formula!==''?' ('.$formulaDisplay.')':''),false,[0.38,0.43,0.52]);
        $textRight(537,670,7,'CERTIFICATO N.',false,[0.38,0.43,0.52]);
        $textRight(537,655,10,$number,true);
        $months=[1=>'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
        $issue='Vertemate con Minoprio - '.date('j').' '.$months[(int)date('n')].' '.date('Y');
        $textRight(537,634,7,'LUOGO E DATA EMISSIONE',false,[0.38,0.43,0.52]);
        $textRight(537,619,9,$issue,true);

        $rect(58,395,479,194,true,$pale); $rect(58,395,479,194,false);
        $text(76,566,8,'DATI INTESTATARIO',true,$green);
        $row(542,'Intestatario',(string)$r['customer_first_name'].' '.(string)$r['customer_last_name']);
        $row(521,'Codice fiscale',(string)$r['fiscal_code']);
        $row(500,'Indirizzo',(string)$r['address']);
        $row(479,'Citta',(string)$r['city'].' ('.(string)$r['province'].') - '.(string)$r['postal_code']);
        $row(458,'Regione',(string)$r['region']);
        $row(437,'Email',(string)$r['email']);
        $row(416,'Telefono',(string)($r['phone'] ?? ''));

        $rect(58,160,479,214,true,$pale); $rect(58,160,479,214,false);
        $text(76,351,8,'PRODOTTO REGISTRATO',true,$green);
        $type=($r['product_type'] ?? '')==='multi'?'Multi Split':'Mono Split';
        $combination=(string)($r['combination'] ?? $r['code']);
        $series=str_contains($combination,'ISPT')?'Serie ISPT-R32':(str_contains($combination,'WTMC')?(str_contains($combination,'BLK')?'Serie WTMC-R32 COLOR':'Serie WTMC-R32'):'Serie WTZ-R32');
        $row(327,'Tipologia',$type); $row(306,'Serie',$series);
        $text(72,285,8.2,'COMBINAZIONE',false,[0.38,0.43,0.52]); $fitText(220,519,285,8.2,6.8,mb_strtoupper((($r['outer_unit'] ?? '')!==''?(string)$r['outer_unit'].' + ':'').$combination,'UTF-8'),true);
        $row(264,'Data fattura',date('d/m/Y',strtotime((string)$r['invoice_date'])));
        $line(76,244,519,244); $text(76,225,8,'NUMERI DI SERIE',true,$green);
        $sy=204; foreach($units as $unit){$row($sy,$unit['unit_type']==='outdoor'?'Unita esterna':'Unita interna',(string)$unit['serial_number']);$sy-=14;if($sy<160)break;}

        $systemDescription = (int)$r['warranty_years'].' ANNI TOTALI'.($formula!==''?' ('.$formulaDisplay.')':'').' - '.strtoupper($type.' '.$series);
        $fullCombination = (($r['outer_unit']??'')!==''?(string)$r['outer_unit'].' + ':'').$combination;
        $flow(58,537,130,8.5,[
            ['text'=>'IDEMA Clima S.r.l. attesta l’estensione della garanzia per'],
            ['text'=>$systemDescription,'bold'=>true],
            ['text'=>'relativa alla combinazione'],
            ['text'=>$fullCombination.',','bold'=>true],
            ['text'=>'alle condizioni riportate nel documento di garanzia di riferimento pubblicato sul sito www.idemaclima.it nella sezione garanzia.'],
        ]);
        $line(58,47,537,47);
        $textCenter(297.5,31,7.2,'IDEMA CLIMA® S.r.l. - P. IVA 03293510966',true,[0.38,0.43,0.52]);
        $textCenter(297.5,19,6.8,'S.S. dei Giovi, 31 - 22070 Vertemate con Minoprio (CO) - www.idemaclima.it',false,[0.38,0.43,0.52]);

        $stream=implode("\n",$commands);
        $logoObject=$logoData!==false
            ? '<< /Type /XObject /Subtype /Image /Width 120 /Height 80 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($logoData).' >>'."\nstream\n".$logoData."\nendstream"
            : '<< /Length 0 >>'."\nstream\n\nendstream";
        $objects=[
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> /XObject << /Im1 6 0 R >> >> /Contents 7 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            $logoObject,
            '<< /Length '.strlen($stream).' >>' . "\nstream\n" . $stream . "\nendstream",
        ];
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        return $pdf."trailer << /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }

}
