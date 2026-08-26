<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Upload
{
    private const IMAGE_MAX_BYTES = 8 * 1024 * 1024;
    private const PDF_MAX_BYTES = 25 * 1024 * 1024;

    /** @return array{path:string,filename:string,mime:string,size:int}|null */
    public static function image(string $field, array &$errors): ?array
    {
        return self::contentImage($field, 'products', $errors);
    }

    /** @return array{path:string,filename:string,mime:string,size:int}|null */
    public static function contentImage(string $field, string $bucket, array &$errors): ?array
    {
        $bucket = self::safeBucket($bucket);
        return self::store($field, $bucket, self::IMAGE_MAX_BYTES, [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ], true, $errors);
    }

    /** @return array{path:string,filename:string,mime:string,size:int}|null */
    public static function pdf(string $field, array &$errors): ?array
    {
        return self::contentPdf($field, 'documents', $errors);
    }

    /** @return array{path:string,filename:string,mime:string,size:int}|null */
    public static function contentPdf(string $field, string $bucket, array &$errors): ?array
    {
        $bucket = self::safeBucket($bucket);
        return self::store($field, $bucket, self::PDF_MAX_BYTES, [
            'application/pdf' => 'pdf',
        ], false, $errors);
    }

    public static function hasFile(string $field): bool
    {
        return isset($_FILES[$field])
            && is_array($_FILES[$field])
            && (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    public static function removeManaged(?string $publicPath): void
    {
        if (!$publicPath || !str_starts_with($publicPath, '/uploads/')) return;
        $root = realpath(dirname(__DIR__, 2) . '/public/uploads');
        if ($root === false) return;
        $candidate = dirname(__DIR__, 2) . '/public' . $publicPath;
        $real = realpath($candidate);
        if ($real !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real)) @unlink($real);
    }

    private static function safeBucket(string $bucket): string
    {
        $bucket = strtolower(trim($bucket));
        return in_array($bucket, ['products','documents','catalogs','gallery','references','campus'], true) ? $bucket : 'misc';
    }

    /** @return array{path:string,filename:string,mime:string,size:int}|null */
    private static function store(string $field,string $bucket,int $maxBytes,array $allowedMime,bool $mustBeImage,array &$errors): ?array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
        $file=$_FILES[$field];$error=(int)($file['error']??UPLOAD_ERR_NO_FILE);
        if($error===UPLOAD_ERR_NO_FILE)return null;
        if($error!==UPLOAD_ERR_OK){$errors[]='Caricamento file non riuscito (codice '.$error.').';return null;}
        $tmp=(string)($file['tmp_name']??'');$size=(int)($file['size']??0);$original=(string)($file['name']??'file');
        if($tmp===''||!is_uploaded_file($tmp)||$size<1){$errors[]='File caricato non valido.';return null;}
        if($size>$maxBytes){$errors[]=sprintf('File troppo grande. Dimensione massima: %d MB.',(int)($maxBytes/1024/1024));return null;}
        $finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($tmp);$extension=$allowedMime[$mime]??null;
        if($extension===null){$errors[]='Formato file non consentito.';return null;}
        if($mustBeImage){$imageInfo=@getimagesize($tmp);if($imageInfo===false||($imageInfo['mime']??'')!==$mime){$errors[]='Il file non è un’immagine valida.';return null;}}
        else{$handle=@fopen($tmp,'rb');$signature=$handle?(string)fread($handle,5):'';if($handle)fclose($handle);if($signature!=='%PDF-'){$errors[]='Il file non è un PDF valido.';return null;}}
        $base=pathinfo($original,PATHINFO_FILENAME);$base=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$base)?:'file';$base=strtolower((string)preg_replace('/[^a-zA-Z0-9]+/','-',$base));$base=trim($base,'-');$base=substr($base!==''?$base:'file',0,80);$filename=$base.'-'.bin2hex(random_bytes(6)).'.'.$extension;
        $relativeDir='/uploads/'.$bucket.'/'.date('Y').'/'.date('m');$absoluteDir=dirname(__DIR__,2).'/public'.$relativeDir;
        if(!is_dir($absoluteDir)&&!mkdir($absoluteDir,0755,true)&&!is_dir($absoluteDir))throw new RuntimeException('Impossibile creare la cartella di upload.');
        $absolutePath=$absoluteDir.'/'.$filename;if(!move_uploaded_file($tmp,$absolutePath))throw new RuntimeException('Impossibile salvare il file caricato.');@chmod($absolutePath,0644);
        return ['path'=>$relativeDir.'/'.$filename,'filename'=>$filename,'mime'=>$mime,'size'=>$size];
    }
}
