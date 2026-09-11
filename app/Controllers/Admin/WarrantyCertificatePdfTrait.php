<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

trait WarrantyCertificatePdfTrait
{
    private static function buildCertificatePdf(array $r, array $units, string $number): string
    {
        $commands = [];
        $layout=self::certificateLayout();
        $fontScale=(float)$layout['font_scale'];
        $pdfColor=static function(string $hex):array{
            $hex=ltrim($hex,'#');
            return [hexdec(substr($hex,0,2))/255,hexdec(substr($hex,2,2))/255,hexdec(substr($hex,4,2))/255];
        };
        $mainColor=$pdfColor($layout['text']);$mutedColor=$pdfColor($layout['muted']);
        $enc = static function (string $value): string {
            $value = iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: $value;
            return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$value);
        };
        $text = static function (float $x,float $y,float $size,string $value,bool $bold=false,?array $color=null) use (&$commands,$enc,$fontScale,$mainColor): void {
            $size*=$fontScale;
            $color??=$mainColor;
            $font=$bold?'F2':'F1'; $commands[]=sprintf('BT /%s %.1F Tf %.3F %.3F %.3F rg %.1F %.1F Td (%s) Tj ET',$font,$size,$color[0],$color[1],$color[2],$x,$y,$enc($value));
        };
        $textRight = static function (float $right,float $y,float $size,string $value,bool $bold=false,?array $color=null) use (&$commands,$enc,$fontScale,$mainColor): void {
            $size*=$fontScale;
            $color??=$mainColor;
            $font=$bold?'F2':'F1';
            $raw=iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: $value;
            $width=strlen($raw)*$size*($bold?0.56:0.52);
            $commands[]=sprintf('BT /%s %.1F Tf %.3F %.3F %.3F rg %.1F %.1F Td (%s) Tj ET',$font,$size,$color[0],$color[1],$color[2],$right-$width,$y,$enc($value));
        };
        $textCenter = static function (float $center,float $y,float $size,string $value,bool $bold=false,?array $color=null) use (&$commands,$enc,$fontScale,$mainColor): void {
            $size*=$fontScale;
            $color??=$mainColor;
            $font=$bold?'F2':'F1';
            $raw=iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: $value;
            $width=strlen($raw)*$size*($bold?0.56:0.52);
            $commands[]=sprintf('BT /%s %.1F Tf %.3F %.3F %.3F rg %.1F %.1F Td (%s) Tj ET',$font,$size,$color[0],$color[1],$color[2],$center-($width/2),$y,$enc($value));
        };
        $fitText = static function (float $x,float $right,float $y,float $maxSize,float $minSize,string $value,bool $bold=false,?array $color=null) use ($text,$fontScale): void {
            $factor=$bold?0.56:0.52;$size=$maxSize;
            $encoded=iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: $value;
            while($size>$minSize && strlen($encoded)*$size*$fontScale*$factor>($right-$x))$size-=0.2;
            if(strlen($encoded)*$size*$fontScale*$factor>($right-$x)){
                $maxChars=max(4,(int)floor(($right-$x)/($size*$fontScale*$factor)));
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
        $row = static function (float $y,string $label,string $value) use ($text,$fitText,$mutedColor): void {
            $text(72,$y,8.2,strtoupper($label),false,$mutedColor);
            $fitText(220,519,$y,10.2,7.2,mb_strtoupper($value,'UTF-8'),true);
        };
        $wrap = static function (string $value,int $width): array {
            return explode("\n",wordwrap($value,$width,"\n",true));
        };

        $flow = static function (float $left,float $right,float $y,float $size,array $segments,float $leading=12.5) use ($text,$fontScale): float {
            $plain=trim(implode(' ',array_map(static fn(array $segment): string => trim((string)($segment['text'] ?? '')),$segments)));
            $words=preg_split('/\s+/', $plain) ?: [];
            $line='';
            foreach ($words as $word) {
                if ($word==='') continue;
                $candidate=$line===''?$word:$line.' '.$word;
                $encoded=iconv('UTF-8','Windows-1252//TRANSLIT',$candidate) ?: $candidate;
                if ($line!=='' && strlen($encoded)*$size*$fontScale*0.56>($right-$left)) {
                    $text($left,$y,$size,$line);
                    $y-=$leading;
                    $line=$word;
                } else {
                    $line=$candidate;
                }
            }
            if ($line!=='') $text($left,$y,$size,$line);
            return $y;
        };

        $green=$pdfColor($layout['accent']); $orange=$pdfColor($layout['badge']); $pale=$pdfColor($layout['panel']);
        $warrantyAccent=(int)$r['warranty_years']>=10?[1.000,0.549,0.000]:[0.000,0.443,0.745];
        $commands[]=sprintf('%.3F %.3F %.3F rg 0 836 450 6 re f',$green[0],$green[1],$green[2]);
        $commands[]=sprintf('%.3F %.3F %.3F rg 450 836 145 6 re f',$warrantyAccent[0],$warrantyAccent[1],$warrantyAccent[2]);
        $logoPath=dirname(__DIR__,3).'/public/logo-idema-pdf.jpg';
        $logoData=is_file($logoPath)?file_get_contents($logoPath):false;
        $warrantyLogoData=base64_decode((int)$r['warranty_years']>=10
            ? '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'
            : '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',true);
        if($warrantyLogoData===false)$warrantyLogoData='';
        if ($layout['show_logo'] && $logoData!==false) $commands[]='q 120 0 0 80 58 719 cm /Im1 Do Q';
        else { $text(58,755,29,'IDEMA',true); $text(58,740,7,'QUALITY HAS A NAME',false,$mutedColor); }
        if($layout['show_badge'] && $warrantyLogoData!==''){
            $commands[]='q 74 0 0 74 461 730 cm /Im2 Do Q';
        } elseif($layout['show_badge']){
            $rect(459,741,78,58,true,$warrantyAccent);
            $textCenter(498,766,19,(string)(int)$r['warranty_years'],true,[1,1,1]);
            $textCenter(498,750,7,'ANNI DI GARANZIA',true,[1,1,1]);
        }
        $line(58,712,537,712,$warrantyAccent);
        $text(58,674,8,mb_strtoupper($layout['eyebrow'],'UTF-8'),true,$green);
        $text(58,642,22,$layout['title'],true);
        $formula=trim((string)($r['extension_formula'] ?? '')); $formulaDisplay=(string)preg_replace('/\s*\+\s*/',' + ',$formula);
        $coverage=strtr($layout['coverage'],['{anni}'=>(string)(int)$r['warranty_years'],'{formula}'=>$formula!==''?'('.$formulaDisplay.')':'']);
        $text(58,621,11,trim($coverage),false,$mutedColor);
        $textRight(537,670,7,'CERTIFICATO N.',false,$mutedColor);
        $textRight(537,655,10,$number,true);
        $months=[1=>'Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre'];
        $issue='Vertemate con Minoprio - '.date('j').' '.$months[(int)date('n')].' '.date('Y');
        $textRight(537,634,7,'LUOGO E DATA EMISSIONE',false,$mutedColor);
        $textRight(537,619,9,$issue,true);

        $type=($r['product_type'] ?? '')==='multi'?'Multi Split':'Mono Split';
        $combination=(string)($r['combination'] ?? $r['code']);
        $series=str_contains($combination,'ISPT')?'Serie ISPT-R32':(str_contains($combination,'WTMC')?(str_contains($combination,'BLK')?'Serie WTMC-R32 COLOR':'Serie WTMC-R32'):'Serie WTZ-R32');
        $systemDescription = (int)$r['warranty_years'].' ANNI TOTALI'.($formula!==''?' ('.$formulaDisplay.')':'').' - '.strtoupper($type.' '.$series);
        $fullCombination = (($r['outer_unit']??'')!==''?(string)$r['outer_unit'].' + ':'').$combination;
        $cursor=589.0; $gap=12.0*(float)$layout['density'];
        foreach($layout['order'] as $block){
            if(empty($layout['blocks'][$block]))continue;
            if($block==='customer'){
                $bottom=$cursor-194;$rect(58,$bottom,479,194,true,$pale);$rect(58,$bottom,479,194,false);
                $text(76,$cursor-23,8,mb_strtoupper($layout['customer_title'],'UTF-8'),true,$green);
                $values=[['Intestatario',(string)$r['customer_first_name'].' '.(string)$r['customer_last_name']],['Codice fiscale',(string)$r['fiscal_code']],['Indirizzo',(string)$r['address']],['Citta',(string)$r['city'].' ('.(string)$r['province'].') - '.(string)$r['postal_code']],['Regione',(string)$r['region']],['Email',(string)$r['email']],['Telefono',(string)($r['phone']??'')]];
                $y=$cursor-47;foreach($values as [$label,$value]){$row($y,$label,$value);$y-=21;}$cursor=$bottom-$gap;continue;
            }
            if($block==='product'){
                $bottom=$cursor-214;$rect(58,$bottom,479,214,true,$pale);$rect(58,$bottom,479,214,false);
                $text(76,$cursor-23,8,mb_strtoupper($layout['product_title'],'UTF-8'),true,$green);
                $row($cursor-47,'Tipologia',$type);$row($cursor-68,'Serie',$series);
                $text(72,$cursor-89,8.2,'COMBINAZIONE',false,$mutedColor);$fitText(220,519,$cursor-89,8.2,6.8,mb_strtoupper($fullCombination,'UTF-8'),true);
                $row($cursor-110,'Data fattura',date('d/m/Y',strtotime((string)$r['invoice_date'])));
                $line(76,$cursor-130,519,$cursor-130);$text(76,$cursor-149,8,mb_strtoupper($layout['serials_title'],'UTF-8'),true,$green);
                $sy=$cursor-170;foreach($units as $unit){$row($sy,$unit['unit_type']==='outdoor'?'Unita esterna':'Unita interna',(string)$unit['serial_number']);$sy-=14;if($sy<$bottom+6)break;}$cursor=$bottom-$gap;continue;
            }
            if($block==='legal'){
                $legal=strtr($layout['legal_text'],['{sistema}'=>$systemDescription,'{combinazione}'=>$fullCombination]);
                $flow(58,537,$cursor-15,8.5,[['text'=>$legal]]);$cursor-=74+$gap;
            }
        }
        $line(58,47,537,47);
        $textCenter(297.5,31,7.2,$layout['footer_company'],true,$mutedColor);
        $textCenter(297.5,19,6.8,$layout['footer_address'],false,$mutedColor);

        $stream=implode("\n",$commands);
        $logoObject=$logoData!==false
            ? '<< /Type /XObject /Subtype /Image /Width 120 /Height 80 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($logoData).' >>'."\nstream\n".$logoData."\nendstream"
            : '<< /Length 0 >>'."\nstream\n\nendstream";
        $warrantyLogoObject=$warrantyLogoData!==''
            ? '<< /Type /XObject /Subtype /Image /Width 100 /Height 100 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($warrantyLogoData).' >>'."\nstream\n".$warrantyLogoData."\nendstream"
            : '<< /Length 0 >>'."\nstream\n\nendstream";
        $objects=[
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> /XObject << /Im1 6 0 R /Im2 7 0 R >> >> /Contents 8 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            $logoObject,
            $warrantyLogoObject,
            '<< /Length '.strlen($stream).' >>' . "\nstream\n" . $stream . "\nendstream",
        ];
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        return $pdf."trailer << /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }

}
