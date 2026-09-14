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

    public static function duplicateManaged(?string $publicPath): ?string
    {
        if (!$publicPath || !str_starts_with($publicPath, '/uploads/')) return $publicPath;
        $root = realpath(dirname(__DIR__, 2) . '/public/uploads');
        $source = realpath(dirname(__DIR__, 2) . '/public' . $publicPath);
        if ($root === false || $source === false || !str_starts_with($source, $root . DIRECTORY_SEPARATOR) || !is_file($source)) return null;
        $info = pathinfo($source);
        $copy = $info['dirname'] . '/' . $info['filename'] . '-copy-' . bin2hex(random_bytes(4)) . (isset($info['extension']) ? '.' . $info['extension'] : '');
        if (!copy($source, $copy)) throw new RuntimeException('Impossibile duplicare il file associato.');
        @chmod($copy, 0644);
        return str_replace(dirname(__DIR__, 2) . '/public', '', $copy);
    }

    public static function copyProductImage(string $publicPath, string $model, bool $replaceExisting = false): string
    {
        if ($publicPath === '' || (!str_starts_with($publicPath, '/uploads/') && !str_starts_with($publicPath, '/assets/product-images/'))) {
            throw new RuntimeException('Il percorso dell’immagine candidata non è gestibile.');
        }
        $publicRoot = realpath(dirname(__DIR__, 2) . '/public');
        $source = realpath(dirname(__DIR__, 2) . '/public' . $publicPath);
        if ($publicRoot === false || $source === false || !str_starts_with($source, $publicRoot . DIRECTORY_SEPARATOR) || !is_file($source)) {
            throw new RuntimeException('Il file dell’immagine candidata non è disponibile sul server.');
        }
        $imageInfo = @getimagesize($source);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $sourceMime = (string)($imageInfo['mime'] ?? '');
        if (!isset($extensions[$sourceMime])) throw new RuntimeException('Il file candidato non è un’immagine valida.');
        if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) {
            throw new RuntimeException('La normalizzazione WebP non è disponibile sul server.');
        }

        $base = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $model) ?: 'prodotto';
        $base = strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $base));
        $base = substr(trim($base, '-'), 0, 100) ?: 'prodotto';
        $relativeDir = '/uploads/products/' . date('Y') . '/' . date('m');
        $absoluteDir = dirname(__DIR__, 2) . '/public' . $relativeDir;
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true) && !is_dir($absoluteDir)) {
            throw new RuntimeException('Impossibile creare la cartella delle immagini prodotto.');
        }
        $filename = $base . '.webp';
        $destination = $absoluteDir . '/' . $filename;
        $loader = match ($sourceMime) {
            'image/jpeg' => 'imagecreatefromjpeg',
            'image/png' => 'imagecreatefrompng',
            'image/webp' => 'imagecreatefromwebp',
        };
        if (!function_exists($loader)) throw new RuntimeException('Il formato dell’immagine non è supportato dal server.');
        $sourceImage = @$loader($source);
        if ($sourceImage === false) throw new RuntimeException('Impossibile leggere l’immagine candidata.');
        $canvas = imagecreatetruecolor(600, 600);
        if ($canvas === false) {
            imagedestroy($sourceImage);
            throw new RuntimeException('Impossibile preparare l’immagine prodotto.');
        }
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 255, 255, 255, 127);
        imagefill($canvas, 0, 0, $transparent);
        $sourceWidth = (int)$imageInfo[0];
        $sourceHeight = (int)$imageInfo[1];
        $scale = min(600 / $sourceWidth, 600 / $sourceHeight, 1);
        $targetWidth = max(1, (int)round($sourceWidth * $scale));
        $targetHeight = max(1, (int)round($sourceHeight * $scale));
        $targetX = (int)floor((600 - $targetWidth) / 2);
        $targetY = (int)floor((600 - $targetHeight) / 2);
        imagecopyresampled($canvas, $sourceImage, $targetX, $targetY, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
        $temporary = $absoluteDir . '/.' . $base . '-' . bin2hex(random_bytes(6)) . '.webp';
        $written = imagewebp($canvas, $temporary, 88);
        imagedestroy($canvas);
        imagedestroy($sourceImage);
        if (!$written) throw new RuntimeException('Impossibile convertire l’immagine in WebP.');
        $differentExisting = is_file($destination) && hash_file('sha256', $destination) !== hash_file('sha256', $temporary);
        if ($differentExisting && !$replaceExisting) {
            @unlink($temporary);
            throw new RuntimeException('Esiste già un file diverso chiamato “' . $filename . '”. Se vuoi sostituirlo, seleziona “Sostituisci il file esistente” e ripeti l’approvazione.');
        }
        if ($differentExisting) {
            if (!rename($temporary, $destination)) {
                if (is_file($temporary)) @unlink($temporary);
                throw new RuntimeException('Impossibile sostituire l’immagine già esistente.');
            }
        }
        elseif (is_file($destination)) @unlink($temporary);
        elseif (!rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('Impossibile creare l’immagine associata al prodotto.');
        }
        @chmod($destination, 0644);
        return $relativeDir . '/' . $filename;
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
