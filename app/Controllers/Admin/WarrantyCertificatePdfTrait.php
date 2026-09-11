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
        $measureText = static function (string $value,float $size,bool $bold=false) use ($fontScale): float {
            $regularUpper=['A'=>667,'B'=>667,'C'=>722,'D'=>722,'E'=>667,'F'=>611,'G'=>778,'H'=>722,'I'=>278,'J'=>500,'K'=>667,'L'=>556,'M'=>833,'N'=>722,'O'=>778,'P'=>667,'Q'=>778,'R'=>722,'S'=>667,'T'=>611,'U'=>722,'V'=>667,'W'=>944,'X'=>667,'Y'=>667,'Z'=>611];
            $boldUpper=['A'=>722,'B'=>722,'C'=>722,'D'=>722,'E'=>667,'F'=>611,'G'=>778,'H'=>722,'I'=>278,'J'=>556,'K'=>722,'L'=>611,'M'=>833,'N'=>722,'O'=>778,'P'=>667,'Q'=>778,'R'=>722,'S'=>667,'T'=>611,'U'=>722,'V'=>667,'W'=>944,'X'=>667,'Y'=>667,'Z'=>611];
            $regularLower=['a'=>556,'b'=>556,'c'=>500,'d'=>556,'e'=>556,'f'=>278,'g'=>556,'h'=>556,'i'=>222,'j'=>222,'k'=>500,'l'=>222,'m'=>833,'n'=>556,'o'=>556,'p'=>556,'q'=>556,'r'=>333,'s'=>500,'t'=>278,'u'=>556,'v'=>500,'w'=>722,'x'=>500,'y'=>500,'z'=>500];
            $boldLower=['a'=>556,'b'=>611,'c'=>556,'d'=>611,'e'=>556,'f'=>333,'g'=>611,'h'=>611,'i'=>278,'j'=>278,'k'=>556,'l'=>278,'m'=>889,'n'=>611,'o'=>611,'p'=>611,'q'=>611,'r'=>389,'s'=>556,'t'=>333,'u'=>611,'v'=>556,'w'=>778,'x'=>556,'y'=>556,'z'=>500];
            $upper=$bold?$boldUpper:$regularUpper;$lower=$bold?$boldLower:$regularLower;
            $punct=[' '=>278,'.'=>278,','=>278,'-'=>333,'+'=>584,'('=>333,')'=>333,'/'=>278,':'=>278,'®'=>737];
            $encoded=iconv('UTF-8','Windows-1252//TRANSLIT',$value) ?: $value;$units=0;
            foreach(str_split($encoded) as $char){
                $units+=$upper[$char]??$lower[$char]??(ctype_digit($char)?556:($punct[$char]??556));
            }
            return $units*$size*$fontScale/1000;
        };
        $textRight = static function (float $right,float $y,float $size,string $value,bool $bold=false,?array $color=null) use (&$commands,$enc,$fontScale,$mainColor,$measureText): void {
            $scaledSize=$size*$fontScale;
            $color??=$mainColor;
            $font=$bold?'F2':'F1';
            $width=$measureText($value,$size,$bold);
            $commands[]=sprintf('BT /%s %.1F Tf %.3F %.3F %.3F rg %.1F %.1F Td (%s) Tj ET',$font,$scaledSize,$color[0],$color[1],$color[2],$right-$width,$y,$enc($value));
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

        $flow = static function (float $left,float $right,float $y,float $size,array $segments,float $leading=12.5) use ($text,$measureText): float {
            $plain=trim(implode(' ',array_map(static fn(array $segment): string => trim((string)($segment['text'] ?? '')),$segments)));
            $words=preg_split('/\s+/', $plain) ?: [];
            $line='';
            foreach ($words as $word) {
                if ($word==='') continue;
                $candidate=$line===''?$word:$line.' '.$word;
                if ($line!=='' && $measureText($candidate,$size,false)>($right-$left)) {
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
        $signatureEncoded=@file_get_contents(dirname(__DIR__,2).'/Assets/firma_arisi.b64');
        $signatureData=is_string($signatureEncoded)?base64_decode(trim($signatureEncoded),true):'';
        if($signatureData===false)$signatureData='';
        $logoData=base64_decode(
            '/9j/4AAQSkZJRgABAQAAAAAAAAD/2wBDAAQDAwMDAgQDAwMEBAQFBgoGBgUFBgwICQcKDgwPDg4MDQ0PERYTDxAVEQ0NExoTFRcYGRkZDxIbHRsYHRYYGRj/2wBDAQQEBAYFBgsGBgsYEA0QGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBgYGBj/wAARCAB4ALQDASIAAhEBAxEB/8QAHQABAAMBAQEBAQEAAAAAAAAAAAcICQYFBAECA//EAEcQAAEDBAECAwMGCggEBwAAAAECAwQABQYRBwgSEyExOEGBCRQiUWGzFRYjMnF0doKRtEJSc3WDoaKyN2JyoxcYJTM1Q7H/xAAXAQEBAQEAAAAAAAAAAAAAAAAAAQID/8QAIREAAwEAAgICAwEAAAAAAAAAAAERAgMhEjEiMhNBUWH/2gAMAwEAAhEDEQA/AL/UpSgFKUoBSlKAUpSgFKV+bB9DugP2lKUApTdKAUpSgFKUoBSlKAUpSgFKUoBSlebeMhsGOxBKyC+W61ME6Ds+ShhJ+KyBQHpUr4rReLVf7IxeLHcYtxt8hJUzKiuBxtwAkbSoeRGwRVROoXrRumAZ5d+PMExlk3S3LDEm7XQ9zaFlAV+SaSfpaCh5qIG/cffVlvpEbSLjKWhCCtaglIGySdAV4jOa4dJuqbXHyuyOzlK7UxW57SnSfqCArf8AlWP+bcw8l8iy1rzPOLrckKPd8zL/AIUdP/SyjSAPhXEIDQUHWg33A7C0Ab3+kV1XF/Wc/wAhupUN9TfLt04Z4KeyawxY792lTGrfDMlJU20tYUouKSCO7SUK0N+Z17t1DnRBz1eMxhTeLcxuTs642yOJVrmyV9zr0YEJW0tRO1FB'.
            'KSCdntVo/m17fygPs0Wr9oY/3L9YWZqM15XNRU6f1jdRU8FJz5MVJ/oxLbGb18ewn/OuWndRHOtx7vnXK+UaV6pZl+CP9AFdX0rcKYtzfyJe7Dlc+7RI0C2pmNKtjqG1qWXQjSitChrR9w+NW8j9BXBbP/uvZXI/tLmE/wC1sV1bznqHNLTPS6Jr/fcj6ZV3PIb1cLtNN5loMmfJW+52js0O5ZJ0Nny9Kql1ecn5/wD+anILDDy282+2WlLEeLDgzXY7aQWULUohChtRUs7J92h7q0J404xxTibBzimHMSmbd84XJ7ZMhT6yteu49yvP+iPKsyOrk66yM3P1PR/5Vqscfema11mH2cGdSPJOB8p2Zu55hdbtjsqY1GuEC6SVyUBpawkuIKyShSd9wIIB1ogg1q4PSsOLlbplpuCoUkeG+G23UkfUtCXEKH7qkmtluJ8oRmfB+KZSlzvVcLVHfcO9/lPDAWPgoKFOVfscb/R2PuqiWc9d+WYvzrf7Ha8TsVzxu2TnILYU663If8NRSpwOglI2oHQ7Doa9/nVz83yNnEON79lUhSQ3a7e/NPd6fk21KA+JArE96Q/Lkuy5Kit95anXVHzJUolSj/EmnHlO0u9T0a8cH8/4Zznj0mVYEyIF0g9vz60zCPFY7t9qkkeS0EggKHvGiAalas2vk/nXEdSN5aSshC8fd7k/Xp9nX/6a0lrG8xw1h1ClV+6sec71wpx3ZpGLfMzfbrP8JpMxrxWwy2kqdJTsfW2n1/pVGfDHXZHyrLYGK8mY9Es7851Edi725xRjeKohKQ62slTYJIHcFKAJG9DzosNqjyVhc2lQdmfOV8xrmn8UYmNx5FvYfjR3nHFKDringkpKNeQ7u8pQNK7lNrBKfdOI8xUhUxSl'.
            'KhRWX/XO6491byWXnFuNtWqGG0LJUlGwsntB9NnzOq1ArL3rj9r2d/dUL/auunF7OfJ6Lu9J/saYH+oL++crPDqm9sDPv19P3DdaH9KHsa4J+oL++crO/qm9r/Pv19P3Dda4/sya+qNHuCMLw+B07YTKg4tZo78uwwpEh1uG2FvuLYQpS1q7dqUSSST9dQv1p8E4vM4elcmYzYoduvVlUhyYuEyloS4qldq+9KQApSCpKgr10FD9FheE/ZswD9nLf/LIr1eRLSxfuJMnsklCVtTbVKjqChsfSaUK5px03KjJvp+y53B+pjDb+h0ttC5NxJB9xZfPgrB+zS9/CrufKAezRav2hj/cv1nDbHHGbnBeSohxDzSgof1gsHf8a0c6/FFXTFZ1H1OQRz/2Xq66XyRyz9WQ58nl/wAbMs/uJH8wmtEqxh425WzniW+zLxgd2at0yZHEZ5xyM2+FNhQVrSwQPMDzFSO51p9RaWVqGaxNhJI/9JjfV/0U3xtulztJQ1XP5prJfq59sfN/7aP/ACrVaoYrOk3TA7NcprgckyYDD7qwAnuWptKlHQ8h5k1lf1c+2Rm/9tH/AJVqscXs1yej5uoDGhZpvH17aR2tXzCbVJJHoXW2fBX8dJbPxFXa6Fsn/DnSqxaHHQp2x3GRB7d+YQpQeR/k6QP0VA3U7jHzjov4RzFpr6UK3R7e8sD+i9FStO/s7mT/ABr1fk7smLGV5rhzivoyYse5tAn0Lai0vXwW3/AVp94M560Tj1tZV+LnSZdYDbgS/fJbFrRo6PaVeI5/oaUPjWenHeLi9YlyJkDzfc1YsbW+lWvIOvPtso+PaXD8DVnPlEMq8bJcMwlpflHjv3R9IPvWoNN7+CHf41x/EuK/Mfk3eY8xdbAXdXGo'.
            'jSiPMtx1t+n2d7q/4VcdZGu9H9dAPtMXf9n3/v2a0nrNjoB9pi7/ALPv/fs1o9cZ8W12iVc5zoajRWVvvLPolCUlSj/AGscns3x+jNDrozb8ZOpc48w8FRMbgtxCAdjxnPyrp/Totp/dqBcrw++4TcbbEvLao0ifbIt3jlOwQ0+jvQf+oa0fqIP1V0Foi3Dm3qZjsvdxkZXf/EePr2Nuulaz+hLe/wCFWm+UDwFiLacLzi2xQ2zGC7G/2DQSjXiMD4dro+Irqn4zJzlrLPcIZVa+U+EcS5BlRIci7qhBl+QttKnWX29tugKI2nakqOgfRX21J1UX+T1z7bWUcZS3/wAwpvMFBPuOm3wPj4StfaavRXDSjh1y6hSlKyaFZe9cftezv7qhf7V1qFWYnXVBmxuq5ydIiPtRpVrifN33EFKHu0LCghR8lEH1A9K6cXs58nout0oexrgn6gv75ys7+qb2v8+/X0/cN1oh0oeXRrgn6gv75ys8Oqb2wM+/X0/cN1rj+zJr6o064T9mzAP2ct/8sivW5BurFi4nya8yVJS1DtUmQoq9NJaUa5jgS/WS69OeDMWu8QJrsewQmXkR30OKaWhhCVIUAdhQIIIPoRUP9b3Ldox3g6Rx9bLpHdvmQLSw9HacClsREnucWoA/R7tJQN+vcr6jXNKuG7FTOSxxXZ1/tkJpJLsiUw0lI/rKcSNfxNaMdfyQjpltCB6DII4/7L1Uz6Z8Qdzbqow61horYjTk3OSfcluP+V8/0qSgfpUKub8oB7NFq/aGP9y/XXT+SOWV8WU86deDG+eM3u+PO5K5Yhb4Amh5EUSPE24EduipOvXe6sWr5OaKptSP/FqR9IEf/Dp9/wDi1yXyeP8Axsyz+4kfzCa0SrO9NOI1jKa7'.
            'PPsdsFlxa3WcPF4QorUbxSnt7+xATvXu3rdZT9XPtkZv/bR/5VqtaD+aayX6ufbIzf8Ato/8q1U4vZeT0W75HxkZP8lfb2kNFyRb8at10Z0NkFhDa1f6O8fGqm9IGS/i11e4wVuFLFz8a1OaP53itko/1oR/lWh/Ftoj3/o/xWwyxti4YrHiOD/lcjBB/wAlVk1aJ1ywLkuDcW0kXHH7o26En6P5SO8Dr7NlH+dbx2mjOuoyTerXK05V1b5ZJQvujW1xu1teewAwjS9f4hcNWtvOJnDfkjZNodbCJDlgamvj3+I++h47+0d4HwqiNlgz+SeaoUBwqXMyS+JS4ff3SH9rPwClH4VqB1TR2YnRTm8WM2G2Wrc222hPolIdbAA+AFNdRDPdZUHoB9pi7/s+/wDfs1bjq5zBWH9JeUOsvFqVdG0Whgg6O31dq9f4fiH4VUfoB9pi7/s+/wDfs1IHyiGWEM4ZgzLp+kp+7SEb9yR4TX+53+FRq7LlzBX3pezvAOM+blZxn0mW0zAgOogNxIqpClPuaQToemm+/wAz/WqeOoTqv4Z5X4IvOEWuBkrlwkeG9CkPwUNNtPtrCkqJK9gaBB0PRRqK+Aukm8c24M5mL2XMWC1pmOQ20/M1SHXigJ7lj6SUhO1FPqfNJqx1h+T74sg6Xf8AKMnvCx59rbjURCvglBVr9741dPNrJlakRSrgXPTxr1EYvli3fDhtSxGmn3fNnvybm/0BQV+7WxaSFIBBBB9CPfWN3N/Ho4w56yXCmm3UwYknvhFxRUpUVwd7RKveQlXaT9aTWm3THnT3IXS/i18mKWqcxG/B8pakkd7rB8MrB9/cEpVse8n6qnIr2XjcqJcpSlcTqK8q/wCM47lVqVbMmsduvEMnfzefHQ8j'.
            'f1gKB0ftFerSgPNx/HrJi2Nxcex22Rrba4iSiPEjI7G2kkkkJHu8yT8ap/1E9GGRZ1yFeORcByGK/PuTgfk2e5fkh3hCUfkngCPMJH0Vj96rpUqrTXaI8p9GNOU8Oct8eyHFZFgmQ2tKToy2WFOMq/xmtpI+NcSxHlzZ6Y0aO/JlvKCUtNoU444r0AAG1E1uXoV8zdstzU1UxqBGRIV6vJaSFn97W66rl/w5/jKwdGfT7dOMcZm5zmcFUTJLy0llmE6PykGKD3dq/qcWrRUPcEpB891/HygPs0Wr9oY/3L9WsqEuqrim+8u9P79gxgNOXiFNauMWO4sIEgoCkqb7j5AlK1aJ8tgbI3usLV1WbeZmIo10lcx4dwxyVfb3mirgmJPtiYjSoUcvqCw6F/SAIIGh61cyF1tdPcsgOZRcIn6zapCdfwSaoNN6a+eoBIf4pyNevfHZS8P9CjXNzuKeUbXv8IccZbGA9Su0SND4hGq6vOdO05LTXRr3gfImG8m4qvI8GvTd2tqH1xlPoacb04kAqSQtIOwFJ92vOsv+rn2yM3/to/8AKtVcroQgT7d0yT41xhSob4yCWfCktKaXrw2fcoA1V3qu415Dn9WWT3S3YNkU6DcXWDDlRLe6+2/qO2k9qkJI33JI0dHyrGFNNGtd5NAeDvZqwP8AZ+F9ymsyeqHGfxU6tM1gJbCGZU38JNAenbISHTr95S61C4itVxsfA2G2e7xHIk+HZYjEiO7+c04lpIUk694I1VP+vDiXKbpyBY+Qsaxy4XSI7A+YT1QI6n1MrbWpTalpQCQkpWod3p9HR15U43NF2viRL0U4qck6tLTMWz4jFkiv3Nz6goJ8Jv8A1Og/Cr09V/sa55+oI++RUM9BXFuQ4tZsozbJ7DNt'.
            'TtzLMKAiayplxbLfcta+xQCgkqUkAkDfaasbzVg1y5K4FyXBbPLiRJ11jJZZfl93hIIcSrau0E60k+gqafyGF8SivQF7S93/AGff+/Zrmesa+TMu6yrtaIDT0xy3NRbREjMpK1uL7O9SUpHmSVuka+yrbdM/SrI4RyK4ZZkOTMXa9S4phIYgtKRHYbK0rUdq+ktRKE+4AAeh3U8R8GwyJlz+VRcUsrN8fPc7c0Q2xIWda2XNd29eXrV80tULD8Yc1wRgj3G/TxiuHzGktzokJK5iRo6kOEuOjY9dKURv7KkSlK5HRdHDZXw1xdnOWxsny/CLTebrGaSw1JmNFekJUVBJTvtVoqPqD612cOHEt8FqFBjMxozKQhpllAQhtI9AlI8gPsFf7UoIKUpQCvluVyt9ntEm63WaxChRWlPPyZCwhtpCRtSlKPkAAPWvqqEupvw38ExG03FWrDcsytMO8hR0hcVTxJQv/kUtLYP6aqVI3D24PULxlMnwW3J93gQrg6lmBd7lZ5cSBLWo6SG5LjYbPd7iSAfdupS2K4LmeDYJvTpmsLIkMfgr8CSlPBzySgJaUpKh9RSpKSPqIGqi7i3Lc1f5S41x++XSUGJXGLVynQlkdq5YcaT4qtjffokevvqyi/0sd5V/hMltQrdImu9xbYbU6vsGzpIJOvt8qgGJyHIjHm5WXZzdLPbbRkDEC3y4jaHpERLkZgpajNlCu5a1rISntUdrrysHyrK4fLmQ4FcHs6csUzEX71FRmwjmY06h0NKLamSVeEoOD6LgCkqT5DRqJCkv47y7huSWDDbrFfnR05ilarSzIir719iCtQWUdyWyEpJ+krR9xNdbBvVoucubFt10hy34DvgS22HkrVHc7QrscAP0VaIOj56IqrPF'.
            'eR3208c9Mdktl1kRYF4amMz47ZHbJQiI4tAVse5QBGvfX2cN4DeZGYc0R7dyPlceYzfpVuZdVIaKVvLhtdkp0Bv6TqCpOiNDSRsGq8kTLT6SPPQrmc1zmy4JDtEm9NzFout2jWaP82bCyH31FKCrZGk7HmfPX1GoQsXJ+YZ3i3FOHxLnItuUzLm83lTrOg4w1az2zEnyPb4rvgp3r0d8q6HqlanPYLg7NsloiTXM5tCWJLjfipZWXFBKyg/naJ3o+utUS77F6J0BB9DunkToH/OoHtE3KeOeqS14XdM/vGTWDIrJMuK/w6WSuDIjLR3LbW2hAS2pKztGtDXlXDjkK92/NsOy3Dso5KyGwXvJGLTKn39iKizzmH1qRuMgBDqCDooWlHaoJOyd+bxHkWw2N62K/TrVV2lZFfrX1LSY/J2V5ljUF+8MsYsbehsWKewUJ7Y77nYo/OFr8QEOFJ/NCDUn8tX+12Djpblzya9WIy5TMOM7YmUvT5Ly1/Rjx0KQva16I8hsDZ2NbqQtO6GteR3TY+uoD4SyrKxzJl3H18cy522w7dCu1vGX/N1XBoPLdbWkrYUQtslsFPdpQ+kCK+jO5OZX3qztfHdozi6Y7Y5eKPXCZ+Dkt+MVolJSC0paVBtRBAKtE9oIGidhBSdPLW6Ag+h3VcMbzy449hXKmKZ7yNdwziV3at0LJkMNuXJxqQ00402EBtSXX+5ZbBCCVdwOtivzjTLcut3O12waZIztVllYw5fIYzURjMZebfS0otqZJPhKDg+i4ApKk+Q0aviKWP2PrFfoIPoapkxk/LEPo+xXnVPKl3kX9TsRhVukstKtzzDsr5tp1oJClufSCy53g7GhoaqUmJ2V8ZdS2PYxPzq+5VZsls1wlyWbsGlrjyYg'.
            'QvxGC2hPYhaVqHh+YHlqniRaJ72N63SqdWvkrlPJuOY3Jtgb5NmZRLcE6HZ4lnSqwuRi75RfMbUC15F/u7u47Gh9GrhNLLjCHChSCpIPar1G/cftqNQqdP7pSlQorxsrxSw5th0/Fcnt7c+1z2vCfYWSNjewQR5pUCAQoeYIBHpXs0oCv+YcAZvdrLDtMPlu55BZIbiHPxcy5kPRZoQdoRIfj+E84kEA6WVg6HcFV2OTcTyczlY1lUnI52JZpaIioxuWMrQpsocCS8wEvtqC2u5IKe5OxoH7Kk+lWskRDiem3DE4hk1hVesjdVfrnHvTlxdlhcuPNZSjtkIcKfzipHee4EbJAAToD1LHwvGt2dO5rec0yPIb7Isz1kflXBTISWHFoX9BtttKW+0o8u0efcoq2fST6UrERGFk4Oxux2/jiJGul0cRgXjfg8uKb3I8VpTR8bSfPQUSO3XnX0wePYeD8h5NyLbMkvjNvunfcbpYW2m32Hn0shBeQAguhfahP0Eq8yPT3VI1KUQg3hDClyOSM15sn45Mx5eUvpTbLVOSUPsREpT3POt//W4+4kOKR6gJRvzqR87wK2Z9CssW6S5cdNpvMW9MmMUgrdjqKkpV3A/RJPnrR+0V1dKUQ4y/8aWLI+TrVmtyelLkW62zLWmICkMvNSe0Od/l3b0nQ0R6muItvThaoNvx60yM/wAvn2fGbjGn2O2SHmPBheAvuQg9rYU6NbQCskpSfLR86mqlKxERleuHTkuWIm5Jn+T3SxNXNu7NY48Y6YqH21hxtJWloOqaSoBQQV+oGyR5V7nIvHVt5FsEGFLuVwtU22z2rnbrnblpS/Ekt77Vp7kqSoaUoFKgQQTXY0pRCPcL4mh4jyDc83fyi/X693WCzCmybo42'.
            'Q4GlrWlSUoQlLeu8jtSAnQ9Nkk+u/gNrkc0ReS1y5YuMazuWZMcFPglpbodKiNb7tpA9da91dXSlEIqvfAWJ35vM/nlzu7b2UXOLeFSI7qEOQJUZCEsuMHt8tFtJ0ru35+6vpx3hyPZ+Q1Z3d8zyLIr+7aHbM9JuKmQhTC3EOAIbbbSlvtKPLtHn3KKtnREmUpWIiLDwTjSunW28Nm63X8EQFMLbl9zfzhXgyBITs9vb5qGj5en8a6e74Ba7zyxjefyJctE+wRpcaOwgp8JxMkJCysEb2Owa0R797rrKUrEIptXCjuMzjGw3krLcfxwyjL/F+KYzsdoqcLi22VusqcabUonaArQ7j29tStSlKEoKUpUKKUpQClKUApSlAKUpQClKUApSlAKUpQClKUApSlAKUpQClKUApSlAf//Z',
            true
        );
        if($logoData===false)$logoData='';
        $warrantyLogoData=base64_decode(
            (int)$r['warranty_years']>=10
                ? ('/9j/4AAQSkZJRgABAQAAAAAAAAD/2wBDAAUEBAQEAwUEBAQGBQUGCA0ICAcHCBALDAkNExAUExIQEhIUFx0ZFBYcFhISGiMaHB4fISEhFBkkJyQgJh0gISD/2wBDAQUGBggHCA8ICA8gFRIVICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICD/wAARCABkAGQDASIAAhEBAxEB/8QAHAAAAwEBAQEBAQAAAAAAAAAAAAUGBwQDAgEI/8QAQBAAAQMDAQUGAwQHBwUAAAAAAQIDBAAFEQYSITFBUQcTYXGBkSIyoRRCUrEVIzZ0orLRFiQzQ8Hh8CViY3KS/8QAGwEAAgIDAQAAAAAAAAAAAAAABQYEBwACAwH/xAA0EQABAwMDAQUGBgIDAAAAAAABAgMRAAQhBRIxQSJRYXGBBhMUkaHBI1JisdHwMuEkJXL/2gAMAwEAAhEDEQA/AP7Loorwly40GG5LlupaZaGVKVyrVSgkFSjAFegFRgc17kgAknAHM1H3nX9qtylMwgbg+ncdg4bSfFXP0zUTqbWMy9uLjRiqNb84DYOFOeKv6cPOurRkO3y4VxcEBuZdoyO9YbfJKFDH4eufzFIVx7RrvLj4PTiBz2jmYz2R+0801M6Mm3Z+IvATx2R495/eOK452u9QzSoNykxGz92OkD+I5Nedntl91U9I2Lio9yEla5DyyN5OBz6U61hGee0vbLjMtIiXAqIe7lvCUJ34CunLAJ617afguMaCDzdwjW9+XLS6l2QvZBShQwPHen60v/C3D1+WrtxTiQndyU4IwIPGSBRf37LdqHLdAQSdvf1ycc4FQjwlw5bsdxbjbzSyhQCiCCDg1Qpf1fZ7NGvCJ76Y'.
            'b+NjLu2BnOMpVwzimGsrMJd9gXC3KQ6zdSlvbaOU95wyD4j8jT65us3JF60pHSCIUNtTAHErRvI/lFa2+lLZdfR7xSSnCCDG4kFQ+YHzNbO3yXENK2Ag5VI4EgH6n6UotfaVLaKW7vDS+g/5rPwq88HcfpV/a7zbbzH763yUugfMngpHmDvFZhrAIt9qslgQBtR2O+d67Sv99quZqzToduY1Bp24GV3aR3/cjDjCuYKeaf8AnCitprN/Zvqt3fxggAq4ChgTHft6/aoD+m2lw0HUfhlRMdxzie6en3raKKktJ6xZviBDl7LNwSOA+V0dU+PUVW1YNneM3jQeYVKT/YPjSlcW7lu4W3RBFFFFFS6j0EgAkkADiTWM6x1Mu93ExoyyLfHVhAH+Yr8Z/wBPDzq21/ejbrEITK9l+blGQd6UD5j67h6ms+kaYkRrbAddkN/bp60hmEB8eyrgo9OXvVd+1F6+8TY2wwkAr9eB9464pv0O2abAunuThP3P++lUUeDpVzR9tfkwXgh5RadlsnLjT3RQHEHluPLdvoRpu5aQu7d7ivolwGT+u+4sNnjkHju37uY4V96ZE7S86Rbr6z9liPguNvrG00lxIyCDw3gcOO4UrsFjnammyp9xmui37e0+8SR3xHIDgMD2oeEoWlkIZh/gR2SgoHKu8HByOJz0qZKklwqc/C8e0FBXQdxGRg92K7UXu6L1NcGtMhy7wZBKiy8hSm0kjfx4DPkCK6V6M1PdYjDFynRIsdjPdMITkN55AJGPqaq4V30pboiYsK4wGGU8EpdT7nqfGm8OfCuDSnYMpqS2k7JU2oKAPSmK30hi4BTcv7yZO1KoSJMkATMT4+lB3dQdZO5lraBGSM4xJ6T6VCo0Nf4zDLcTUmEx1940'.
            '2UKCUK37wMnqeXOliLVqnTeoDfn4f6S3qLymF52wob8jGR14cq0qfc7fbGQ9cJbcdB3ArVjPkOJrnt+oLNdXS1AuDTzg37AJCvY763d0XT0uJbadKHAQUjdORx2VE1q3qV2UKWtsKQcE7Yx1yIqEgwrPq+dPvM2RIXIByLeyQFhCRgbz83DliuMaugWVbrOn9PoiOfItySolZxyIzn0zVjqDSbc5f6TtK/sN2bO0l1s7IcPRXj4++alIM+E/cJN0v0Ncu+RiGm4DTGNopHzkDOTniTuHIcKCXdvcWjgRKUOKJJcgHcOSZMlKv0jnpiidu61cIKoKkAAbJOO4QMEeJ460jk2e9sW/+1D4TF23wtOD3a8k5Ckp5DPr6Vp2ktRJv9q2nSlMxjCXkjdnooeB/PNZxdmtWahmfaZVrmFI/wANsMqCGx4Z/PjXnZ37hpPUkZ6fHdjIcGHULGNpsnBPoRn0oZp18dNvN7aVe5VAUVDk/m8PLOKmXlqLy22qKfeDIA6fp8f5rbaK/EkKSFJIIO8Ec6Kt2q/rGNdTzM1bIQDlEUBlI8RvP1J9qqFxX79Og6p03IjOSmWkocjSPuEAj04n8wazm4PmTdJchR3uvLXnzUa6XLXcYNni3n42mJKlISpJKSMdfA78eVUi1qKlXNw6pBWlR3GDBTB7JBzxMcVZi7NIZaQlW0gbRIkGRkEeMVZXlqa7brbpOTPE26yZPfPKCtoMJ37vIAk+nlV43bmodiNthN4QhktoT1ODx8Sazbs2jB/UciWv4iwycE7/AIlHGfYGtYqwPZ9KbppV4oQVdkZkhKcc9STJNKerKLDibYGduT0lRzx5RFYNc9N3mzRUSLjFDLa1BAPeJVvwTjcfCrzs8kNxdJXCS8rZaZfUtR6A'.
            'ISTXr2mfs7F/eh/Iqpq2yCx2VXcJOC7KS36HYz9M0rt27Wj6ssMkkIQVZ/8AM9AKNreXqNgkuQCpQGPOkkuTc9U6gK0NrekPqw00DuQnkB0AHE+tfFxtF309LZVLaVGcPxtOIUCMjoRzFVvZhFQubcZqgCptCG0nptEk/wAoqj7QIqJGkH3ikFUdaHEnpv2T9DUdnRTd6YvVHFn3mVD0OfGcH6V2c1IMXqbFCRswPnx9qYaWvJvmn2ZbmO/Tlt4D8Y5+owfWp3XMGRbpMfVNrWWZLR7t1SQDkEEJURz6eo6Vydl8g/8AU4pO4d24B7g/kKt77ETO09PiqGe8ZVjzAyPqBTiyVaroqVqPbiQeoUmYPnI+tLzkWGpFKf8AGcjptPI+tS0OZKuelIV3u92k21qOpYfU2dgyk/dIx14bvHFQ+ptQqv05CkshqKwChlB3qx1UeZOK4ftlzujUG1d6t5DWG2GRyJP++M8hXnc7dItNzft8oAOtKxkcFDkR4EVXuoao9dWwSgHZ2QpR5UoD6ccDnk5pttLFth4lRG7JSB0E/wC/TgYrY9GTlXDSUNxasuNAsrPik4H0xRUBpe/uWy1Ox0qwC8V+6R/Sin3TvaC3Fo2HT2gAD6Uq3mkvG4WWxgkxU9DlybbekyI+z3zbpGFpyDvwQRWt3TVFrTbbgzAnxXpzDKlJbJykkDgOSvIVlepIhhaouMfGAHlKT5K+IfQ0ppBtNWuNJL1sgTJIz0iRjx4+VNT9g1f+7fUeBPnwc+H81ofZo+XLtdO8VtOuoSsnr8Rz+dabWJaKuabZqqOtxWy0/lhZ6bXA+4FbbVgeyVwHdP2TlJM+ufvSnr7JRd7uigPpioftM/Z2L+9D+RVScVJV2W3Aj7s9BPskVWdpn7Ox'.
            'f3ofyKpVpWAq59nF5hIGXFuqKB1UEpI+ooJqTKntZeaTyWzHntonZOBrTm1q4Cx+9dHZcod1dUZ37TZ+iqo9brCdE3HJ4pQB/wDYrNtG35qxXtS5e0mK+ju3CBkoOcg48N/vTzXOq4Fyt7dstb3foUsOOuAEDdwSM8d+/wBKyx1W2b0BbKlgLAUmOuZjHrWXVg8vVUuBJ2kpM9MRP7UdmCSbhcl8g2gfxH+laPNWlq3yXFfKhtSj5AGpDs2t649ifnLSQZbnw55pTuz75pjri5pt+lZCArDsr9QgefzH2zR3SFfA6GHXcQkq+ZJHzxQvUB8VqZQjvA+UA1E6AvCYEySzMfZZghouqW7gFKsgDB47+nhXfrvUDE+1w0WuUy/GfKu9UkfGCnGEnO9PH6VnlFVujW30WB08Dsnr15mPLpHjTirTGlXQuzyOnTj96aW6K6/HWtAJAXj6CitC0FaGXNL/AGiQjJeeWpPkMJ/MGimaw9mlP2rbxVG4T86C3WspafW3HBilPaVay3OjXZtPwPJ7lw9FDePcZ9qiodtn3BzYhQ3pCv8AxoJA8zwFbrebWzeLPIt724OJ+FX4FDgfeskiX7UOmX1WlK9kMP5WwpIO0eYB44PHd1zXD2h0xli/+IfJDTnVIk7uo9efniuukXrjtp7pqCtHeYx/cV1Rez+8ujEl2NEdUkqQ0tzaWrHgOHnndVto/UgucT9HTlhNzijYWCf8QDdtDqevvzpNqSZE07FkuwkONXa8JCl94raVHQR8QB5b8+uegpK1pV6Do46iMp2LPaw+2hO7CMgDPME8fpUq3/6u6KLFBVtBLg3SNvToO1GeOsd9cXf+cxuulRuICMdevU46c9Jq51jY5l+tLEWEppLiHg4e8UQMbJHIHrRo'.
            '2xzLDaX4s1TSnHHi4O7USMbIHMDpSCydooDSGr6wtOfhEppOQrHVP9PYVZxL7Z5yAqLc4zmeXeAH2O+mSzd0y8uhqDS/xIiCYPy/ooLcovbZj4RxPYmZAn61G6m0C7KmuXCyqbBdO05HWdkbXMpPj0NKrV2c3V+Wk3VSIsdJ+IJWFrUOgxuHnWormw2k7TkplCeqnAB+dIblriwQElLcr7a9wDcf4snz4fWot5oujIeN0+rb1ImAfTn0FSLbUtRU37hoT0mMj14+dPP7na7cBlEaLGRjfuShIrOnXmtWXObep6HDY7W2oIZTnacOPDeM8T03DrSjVd41DcUMLuMVyBBdyplgjAVjmeZO8cceAr5tVu1HaX2rlbVNllcf7Qp4LHcqRzQsndnO7HHPvQi/1n4y4TbNtEsogkRk/lJT+UYMGJ6xRC0074dovLcHvFcZ47xPeeJ6Vz/2Pvq7Ubo3CwwR3iWyvLmxxzjnu9fCkTDLkiQ2wynbccUEISOZJwKtLZqJlEuXqa43JRmKC2mbc2DgjA2c8gkf6Zrp7PLAp+Wq+ykfqmiUsAj5l81eQ4eflS8nS2Lt9lqzJ7U7pIMJEdrH+M57J4x30XN87btOOXAHZiORk9M8xjP8VodrgN220xYDfysNhGep5n1NFdlFXQhCW0hCRgYqtlKKlFSuTRUnrDSib5G+2QwlFwaTgcg6n8J8ehqsoqPeWbV4yph4Sk/2R412t7hy2cDrZgisOtqmZOqm1aokrbQycO98kkkoGAg9OH/M1V26bK1axqZDXw98hpphCjubRlWP6mqTUWkrff0F1X93mAYS+gcfBQ5j61n4Z1Noh2UUMJLL6NgvpTto54UD90jPOq5XZ3OkO7XwVMEqKlJyoykpG7uifLM5NOCL'.
            'hnUES0Ql0AAJPAgg48488U8KYErU9r0hEaQ7breVLfKhnvXAk5z6nf4nwpVd9NQl6ttiLenFsuZTs93wTj5wM+G/1pfpG6wrVcpk6c+UuGMtLJKSraWTniPLj41Q6DusJ6EIVydbS7AcL8ZS1AblAhQGfM+9cbZy11EIae2hS1Ej9ITtAT4ApCvMwa6vIfsypxuSEiD4lUkq8wY9KVtabtTh1Q22XlqtacsFShyBznA3700l01cUWvUkOW6AWgvZXkZwlW7Ppxp5pK4MOT7+ZkhtlM2OtWXFhIJKju38/iqLHAeVArpxtlNvdWwAMqOPBcifQgeVE2ELcLrDxJEJ+qYP1rQ5VlteobjLjt6oenXNIWtpKhloAH5QeG7dwPjSBepkrtTtmXbGm7f3WyhltRCkOjf3hVxO/iK9IWonGoqIdlsUdm4La7gyGgVuqHMgdT13040/2dvvKRJvhLLXER0q+NX/ALEcPTf5UWAevlgaantKneQDtg8AlcnvnvxgxUElu1STeHsiNoxMjqAmPCO7PfSPS2l5N/lhawpqA2r9Y7w2v+1Pj+VbPHjsxYzcaO2ltptIShCeAAojx2IsdEeM0lplsbKUIGABXrVgaNozWltbRlZ5P2HhSlqOorvVycJHA/vWiiiij1CqKKKKysor8ICgUqAIO4g86KKysqfnaN09cFKW5ASy4fvsHuz7Dd9Kgb9pe32x1SY7shQH41JP+lFFV17TWrDfaQ2AT3AU46I+6vClEjzNIotuZffDa1rAJ5Y/pWhWjQVhcjpkSPtD5P3VuYH8IFFFL2g27Tr4DiAR4gGi2quuNtEoUR5Gq6BbLfbWi3AhtR089hOCfM8TXZRRVxoQltISgQPCq6UpSzuUZNFFFFb1rRRRRWVlf//Z')
                : ('/9j/4AAQSkZJRgABAQAAAAAAAAD/2wBDAAUEBAQEAwUEBAQGBQUGCA0ICAcHCBALDAkNExAUExIQEhIUFx0ZFBYcFhISGiMaHB4fISEhFBkkJyQgJh0gISD/2wBDAQUGBggHCA8ICA8gFRIVICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICD/wAARCABkAGQDASIAAhEBAxEB/8QAHAAAAgMAAwEAAAAAAAAAAAAAAAcFBggBAwQC/8QARhAAAQMDAgQDAwgEDAcBAAAAAQIDBAUGEQAHEiExQRNRYRQigQgVMkJScZGhFyOCwRYzVmJykpOUorGy0hgkNkNEU9Pi/8QAGwEAAgIDAQAAAAAAAAAAAAAABQYDBAABBwL/xAAxEQABAwMDAwIEBgIDAAAAAAABAgMRAAQhBTFBEhNRYXGBocHwFCIjkdHhJLEyYvH/2gAMAwEAAhEDEQA/ANl6NGvBWKxTaDR5FWq0pEWHHTxLcX+QA7k9ABzJ1sAqMDetFQSJO1e5a0oQpa1BKUjJJOAB56Ud3782zQluQ6Ig12YjIKml8LCD6uc+L9kEeuk1uLuzWL1kOwYanKdQgcJjJVhT4+06R1/o9B6nnqW2gpFBqlHuCQmiR6tdNOa9ohR5pKmXE45DgHIniGOf2k9NMzWkpYa790J9B9TSs7rCrh78PaED/sfoKh63vbf1ZWsM1VFLYJ5NwGwjH7Zyr8xrotK2r13NlzgzX3FCIEF12fKdIJWSABjPPkdXHdmnS5W2tt1+rWsilV5S1JlmJHKW2muYSHCM8JPuEAnllQ17bEor8LY0TGK7T6DMqtURKRKqD3hoLbKxwpHmSWyceROineabtgthISSenzz6'.
            'b4FCQw65dFD6ypIHV44wM7ZNJCUmqUiqSYL7z8eXFdUy4kOKBSpJIPfzGr83O3ZtO0abdjddmopE3h8IrkeME5zwhTa84yE5Hbpqe3etBFVvWiV6gOMyYtzqRH8aOoLbL+QnII7FPP8AZOrvckqLcLN37XQG0rFGpDDkFIHMutDiKR682k/jrbt4lxDaukEHKpGwkA/M/KtM2Km1up6yCMJg7mCR8h86q1tfKKqUdTbF10lEpk/+TDHhuY8ygnhV8CNPe27st+7Kf7bQak1LQMcaB7q2z5KSeafjrNO7IaoNsWbYzSUhynwvapPLn4rnb8eM/Eaj4dp1uj0CDfm31fVUiw0DOEVJS9DcxlSVNn6SPvHTngjnqi9p9s+2HUfkKiY8HxPifuavsajdW7haX+oEgT5HmPMc/Stg6NLDa/diFezAplRDcOvNJyW0nCJKR1U36+ae3UZHRn6WH2HGFltwQRTWw+3cNhxoyDRo0aNQVPXytaG21OOLCEJBKlKOAB5nWPt2Nxnr2uBUWE6pNChLKYyByDyuhdV9/byHqTpx793gqh2c3QYbvBMq/EhZB5oYT9P+tkJ+4q0jZ228+nW9Q5Miex89Vx5tMSkJH63wl5w4o9ueOWOWeuQRpr0a3bbAuXtzhP1NKOt3DrpNszsMq+gq/wACi7YSNprdnVGiy0MylmLJqkQlciNKJxhYAypJI5DBx7vLnnXLe3dw7UXVHvKmzWqrQ4av+cOQ08hhXJfEgnB5EEYOcgctdm3Are2lbqFv3tF+baVOSXo854ByO3IbBIUFdMlI6cjlKeWq7YVj17cyvVCrVqrS1UVcjilyeIpM1aTySlJ5DAxzx7oIA59LBUUlwqc/T9chQVwPBGRg+MVWCUqDYS3+rtj8pSU8'.
            'nyDg5HnNSrV53M7uPXou3KX7rolQUVqiTGVuMIUse8ffI4U5z1IBBxjlnUi7tBuRc9LgwrhrNKpsCFxezQWW+IRweoAQkD8VHT0g06hWlby2YMVmnU2G2p1YbTySlIypR7qOBzJyTqr/AKZdtcf9UM/2Dv8As0MF66ozaNbRmJPiTxPwoqbFlIi8d3nEwPMDmJ9aoLOyl9U6HDj0vcPhbgu+PGYU0tLbTnP3gMkA+8e3c6rjNr7mbeX4b4nUr+EIKnFS3ITvF4wWDxZATxJ7H6OBgacX6ZNtf5UM/wBg7/s12xd29vJs1iHFuRpx+Q4lptAZdHEpRwBzT5nXpN1fCe411A7/AJYxzkAV5VaWBjtPdJG35pzxgk0pqNRrT3XrVbu6sT5z09CitNCiFKXksoSEoAJ+mTj6pABOOuohO69Ds1yTEsOw2aU/zadkVFalPHB6KSDnkexVpu33tdHrT38I7Wd+ZbojnxGpLB8NL6vJeO56cX45GldRq5SJteqFy3xSHqretPUmKxRI0Lh41IH8aoAHiVnOVHknlgH3dXGHWn0FUFSQB+WdvG2CPU7c1SfadYWEyEqJ/wCcb+TnIPoN+Kpc61rzgUP9JksIpanZiXWsEMvcSiSHEIGMJz0HXHPGOetK7X36zfVqiQ8UIqsTDUxpPIcXZYH2Vcz6EEdtZ2uiLunftXNQqdtVdaE5DEdMRxLTCT2SCPxJ5nXzZk+u7W7jU5+vQZFOYlpDclp4Y42FKxx/sqGfgfPVq7thd28KKe4MgDx49f5qnZ3Rs7iUhXbOCT58+n8VsbRrhJCkhQIIPQjvo0j0+1jneeuLrO61TSF8TNP4YTQJ5DgGVf4yrTQepky96zRNzdvZ9OfqcOMhmRTZ55MrAUMcuh94jt2I'.
            'Os71WYqfWp09xWVSZDjpJ7lSif36l37buCjWlTbu/WxoVRccZacbUpChw9CSMYCvex/RPprojloA02gK6SBAnIMjIj1iuatXii66tSSpJMmMEQcEH0mm5d8WryqBbu1tRriaxc9RqPtctwLLgho94hOTzwASQD2Se2NPyh0aDb9CiUamtBqJEbDaE9z5k+ZJySfM6zV8ninJnbg1CqvZcVChkpUTk8bigM5+4K/HWpdK2qy0sWwO2Txk+nttTbpEOoNyRBOBzgevvvVU3IleybXXK8Dg+wOoB9VJKf36xbSKXLrVah0iCEmTMdSy0FqwOInAyew1rreqT7Ps7Wxnm6Gmh8XU/uzrK1mVqJb170quT2XXo8J/xVoaA4jgHGMkDqR30Y0MKTauLQMyY+AoJrxSq7bQswIE/E1dv0A7g/8Arpv97/8AzqSoOxt9U+5qXUJKKf4EaWy85wysnhSsE4HD5DV+j/KLs5xYS/TKuwn7XhNqH5LzphWxfVr3e2o0GqtyHUDiWwoFDqB5lCsHHqOWqr9/qTaT3EQPb+6t2+n6W6sdpcn3/qrINI3euizrfqMDcy2nTDqMZYjynEJByFApQsgjB68Jz1BT5aeWq/e1LbrNg1ymuJ4vGhucP9IJKkn4KA0Csnuy+lR2OD6g70wXzHeYUkbjI9CNqWlIq1TuTa+jXVdd01G3Y0Bx0TnGD4BqTf1CnhwRk4HujJ97HYhK7j365fNbZcbiCLTYKCzEaV7znAcZUtXUk4HLOB6nJMN873Hc0aiWv7U9LajEMQog6BSlcuXc88ZPQcumui5KBPte5JlCqaQJMVfDxJzwuAjIUnPYjB08Wtm2y6SojqyQBwJ+/wDQpCu71x9kJSD04BJ5Mf19TWvtqK4uv7W0aW65'.
            'xPsteyuk9Sps8OT94APx0aTu0F5roNmSoJVy9tWsZ7ZQj9+dGlS7sHA+voGJNN9nqDRt0dZzApR0Gq1C37miz6f4ftUd3hCXUBSFc8FKgex/Hy1q65dy7ZRb1dh0SuUyVWYURbjcdZ4m1KAPJOfdWRz5AnWXb5pZo24VeppTwpZmOFAx9RR4k/koarvppsuLFq96HlHYfvzn75pOtr92w7jKRMn9txj74p+fJzmqkXTcxkOcciSy2+pWAOL31cRwPVQ1o/WM9nribtzc+nuyHPDizQYTyicABeOEn9sJ1szSvrjRRddXBA+WKbNBdC7Tp5BPzzVS3Cs9297SVQmqiIHG826XS14mQkk4xkd8aUX/AA0yP5YN/wBxP/00w909yhYMOnJiR2plQlu58BxRADKfpnI6EnAB+/rjXgou/li1FhPzk9Jo7+PeQ+ypac+ikZyPvA1lqrUGmAq3B6T4AP8Adbu06a9cFNwR1gDckfWKWdc+TvcdPgOyqTVotVW0kq8DwlMuLx2TzIJ9CRpR0ypVCi1aPU6bIXFmxV8bbieRSR2Pp2I7jlrVtd30sWm0112mT1VeZwnw2GGlpBV24lKAAHn1PprJTrrkqW48pPE68srISOqic4A+86ZNMdunkKF2nHEiJ84pX1Rm0YWg2as8wZjxmt32vWkXFaVLriEBHtsdDxQOiVEe8PgcjXprDyI1BqEhw4Q1HcWo+gSSdRNh0d+g7e0OkShwyI8VAdT9lZ5qHwJI+Gq5vTcbdB2wnspc4ZVTHsTKc8yFfTPwRxfiNJSWg5c9pvYqge009LeLdr3XNwmT7x/NJvYm7m6FV6jEq02HEoyYqpS3ZGEltwFKRwq6nOfo8845anN7r8g1y26QxbVShzKbNU57SttILqSj'.
            'hKUEH3kA5z0BOPLSD0afFac2q5F0dxxx/wC1z1OpuptTagYPPO/+qYFkUuVNokh1hJKRJKTgd+BJ/fo07NibfYRta3LlsBSpst19BP2RhA/0aNL93qfQ+tAGxpjs9K7luhZO4qifKJtpUS5IN0MtnwJ7YjvKHZ1H0SfvR/o0oaTb9crr/g0akS56+/gNFQH3noPidbXvS14t4WhOoUohBeTxMukfxTg5pV8D19CdZWo99X5t3LVbDTvhohTeJ2C42FcSs+8gKIyEq68vPI66vaXeOOWvbbgrT58feKoatZNtXfddkIX4E5+81K0zYe75ScVCVT6VJW2pbMV1/jecIHknkBnGTnlnTl2l3ETclLNvVp0N3HTQWnUqUMyEpOOMHuRjCsd+fQ6qG4lWpdgUyoSaM0/Guq7EBx7x3ONyAyR76Un6vvcQAB65I5JA1UIu2Eyi7SncBVTlU2uRuGbHab5cLJICc9wo54s56ciOuoHf8xmblUSQE455844+E1YaH4F7ptUz0gleeOOBnn4xTl3E2jpd9y01MVF+n1NtoNJc/jG1JBJAKCeXU80kde+kvUPk/X3EdIhmn1FHZTcjwz8QsDH46uVm/KASmKzFveE6gnKE1KM1lDhGM8SPMZ58OevQab9Lva0ay0HKZcdPkZ+qH0pWPvSSCPw1TD+oaeO2RKR6SP3q6bfTdRPcBhR3zB/asxxth9xX3Qh2BDipP13ZaCB/VydNvb/Y2m2xPZrNelIqtSZIUy2hHCwyr7QB5qUOxOAPLPPTTerFJjtlyRU4jKB9Zx9KR+JOqRcG89j0RKmo9R+eZmeFEenjxeI9hx/RHP1J9NRr1C/ux20DfwPrUjenafZHuLOR5P0q+zp0Sm09+fPkNxorCCtx1w4ShI6k'.
            'nWbanVKTufcFZuy5ZUqHZlttpbZZYH655TisA47FRGT5DhHLmdVzdC7L/r/gG4aPMoVGcVxRoimlIQsjmCpRA41Y88Y7Aai2bHvVuiEQXUroc+EmpPSGpITFUlAOEuKOBxpORwnv+OiFlp6bdHcWsBR2M7DmD5ih19qSrhztNtkoTkiNzxI8Tmp+6dm6qHjVrHiSKnb70NuY0p1aQ6AoE8AScFRAwemeeOZ0r6fBlVOpxqbCbLkqU6llpA7qUcD/AD027Tv9uK6/f10XS7Kq8VlUKFQ2EcCXRwJCVKA91Kc5J5DmM+Q1N7C2S9Nqj1+VVnDaVLTCBHJbhJ43APIZKR6k+WiAu3bVlZfz0wAc5OcevGRQ78GzdPIFvjqkkYwPPpzg0+aBR49Btun0WMP1UJhDIP2sDBPxOT8dGpPRpCUoqJUdzXQkpCQEjYUaU+7u1qbwhfPVFbQivRkY4foiWgfUJ7KH1T8D2IbGjUtu+u3cDjZyKhuLdu4bLTgwaxXbb8Kp7mRntyam80zFIEj2tCipZaGEtL5ZSOXPPke5zppW/WKnurD3HZjjw/bGosaCys4Sw0FLxn81H1+GmBuBtTQr4bVLz83VhKcImNpzx46BxP1h69R59tIhEbcfZiVUi3CQIs5rwVTENl1kkZ4FpUMFCgSSArHqDprS+1fIlow4AIB2EEHHvFKKrd6wXDolskkqG5kEZ9pq7luh1Pcm2dp6XGalW/Qi47OK0hXtL6W1FXF54Uef85RHYaq92bd0d7dO22aE2E25cakeGWOjfCcOhJOcchxemT5agdqrno9sXFV63WZim5Bp7qIpKFLLjyiDzIBwTw9T56v+yFz0eXRk0a4pTLUqhyFTae484EnhWhSVpTnrgqUcfzh5akdQ9aFS'.
            '25ISIPqTJKvcGPhUbK2LwJbcgFRkegEAJ9iJ+NVmNt5bD69yo8cynnLbQTCUt0ZPClfFxYACveQR21RLMuVq07pYry6WipLjoX4LTi+AJWRgL6Hpz1f9rK9Bfrd9Kq0+PDRV4Dy+OQ6lsKWpauWSeZ/WHlpPD6Iz5aJMJUtTjL0kQPmM/OhdwtCEtPMwDKvkcfKnXWazW53yf2W7mlPVOrXBU/Fpja08TqW0kZUkDnjIIA8ljz1WZO4iVW3Ls2TbDEWiJjeE3DaWpDjMpJz46lkZUePOUkdMDzJm4u7lceotEoFp20yKzDhohiYGfaH8AAHwkge6DgE5z93LVis/YyqVipKuDcOStJfcLy4aFguvKJyS4sck58k8/UaHgtWyVG6ASJJABzPEAegGffaiMO3Kki0UVGACSMRzJPqTj23iqJthtjPvmqJlS0uRqDHX+vkdC6R/22/XzP1fvwNa+hQ4tOgMQYTCI8ZhAbbaQMJQkDAA1xChRKdBZgwIzcaMykIbaaSEpQB2AGvRpZv79d4uThI2FNOn6e3ZNwMqO5++KNGjRobRSjRo0ayso18rQhxCkOJC0KGClQyCNGjWVlUOt7P2BXXFuu0NEJ9R5uwVFg/1R7v5aRF8bbUO3JbjUGVOcSk8g8tCv8kDRo02aO84rClE/GlDWWGkZSkA+1U6l29DnTUsuuvBJOMpKc/mNPy1djLHegMz5yZ85SuZbdkcKP8AAEn89GjRLU3VobJQoj2oZpTTa3QFpB9xTVotu0K34vs9EpMWntnkQw2ElX3nqfjqV0aNIalFRlRk10BKQkQkQKNGjRrzXqjRo0aysr//2Q=='),
            true
        );
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
        $issue='Vertemate con Minoprio (CO) - '.date('d/m/Y');
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
        $textRight(537,131,8.5,'Idema Clima srl',true);
        if($signatureData!=='') $commands[]='q 105 0 0 75 425 53 cm /Im3 Do Q';
        $line(58,47,537,47);
        $textCenter(297.5,31,7.2,$layout['footer_company'],true,$mutedColor);
        $textCenter(297.5,19,6.8,$layout['footer_address'],false,$mutedColor);

        $stream=implode("\n",$commands);
        $logoObject=$logoData!==false
            ? '<< /Type /XObject /Subtype /Image /Width 180 /Height 120 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($logoData).' >>'."\nstream\n".$logoData."\nendstream"
            : '<< /Length 0 >>'."\nstream\n\nendstream";
        $signatureObject=$signatureData!==''
            ? '<< /Type /XObject /Subtype /Image /Width 433 /Height 309 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($signatureData).' >>'."\nstream\n".$signatureData."\nendstream"
            : '<< /Length 0 >>'."\nstream\n\nendstream";
        $warrantyLogoObject=$warrantyLogoData!==''
            ? '<< /Type /XObject /Subtype /Image /Width 100 /Height 100 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($warrantyLogoData).' >>'."\nstream\n".$warrantyLogoData."\nendstream"
            : '<< /Length 0 >>'."\nstream\n\nendstream";
        $objects=[
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> /XObject << /Im1 6 0 R /Im2 7 0 R /Im3 8 0 R >> >> /Contents 9 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
            $logoObject,
            $warrantyLogoObject,
            $signatureObject,
            '<< /Length '.strlen($stream).' >>' . "\nstream\n" . $stream . "\nendstream",
        ];
        $pdf="%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";$offsets=[0];
        foreach($objects as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$pdf.="xref\n0 ".(count($objects)+1)."\n0000000000 65535 f \n";
        for($i=1;$i<=count($objects);$i++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$i]);
        return $pdf."trailer << /Size ".(count($objects)+1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }

}
