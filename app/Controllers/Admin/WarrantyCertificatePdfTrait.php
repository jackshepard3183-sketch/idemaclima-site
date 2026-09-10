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
        $commands[]=sprintf('%.3F %.3F %.3F rg 0 836 595 6 re f',$green[0],$green[1],$green[2]);
        $logoPath=dirname(__DIR__,3).'/public/logo-idema-pdf.jpg';
        $logoData=is_file($logoPath)?file_get_contents($logoPath):false;
        if ($layout['show_logo'] && $logoData!==false) $commands[]='q 120 0 0 80 58 719 cm /Im1 Do Q';
        else { $text(58,755,29,'IDEMA',true); $text(58,740,7,'QUALITY HAS A NAME',false,$mutedColor); }
        if($layout['show_badge']){$rect(459,741,78,58,true,$orange);$textCenter(498,766,19,(string)(int)$r['warranty_years'],true,[1,1,1]);$textCenter(498,750,7,'ANNI DI GARANZIA',true,[1,1,1]);}
        $line(58,712,537,712);
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
