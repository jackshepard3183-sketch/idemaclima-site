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
        $canvas = imagecreatetruecolor(1000, 1000);
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
        $sourceX = 0;
        $sourceY = 0;
        $contentWidth = $sourceWidth;
        $contentHeight = $sourceHeight;

        // Calcola l'ingombro reale del prodotto ignorando sia la tela
        // trasparente sia un eventuale sfondo uniforme incorporato nel file.
        if (in_array($sourceMime, ['image/png', 'image/webp'], true)) {
            $corners = [
                imagecolorat($sourceImage, 0, 0),
                imagecolorat($sourceImage, $sourceWidth - 1, 0),
                imagecolorat($sourceImage, 0, $sourceHeight - 1),
                imagecolorat($sourceImage, $sourceWidth - 1, $sourceHeight - 1),
            ];
            $transparentBorder = false;
            $bgR = $bgG = $bgB = 0;
            foreach ($corners as $corner) {
                if ((($corner >> 24) & 0x7F) >= 120) $transparentBorder = true;
                $bgR += ($corner >> 16) & 0xFF;
                $bgG += ($corner >> 8) & 0xFF;
                $bgB += $corner & 0xFF;
            }
            $bgR = (int)round($bgR / 4);
            $bgG = (int)round($bgG / 4);
            $bgB = (int)round($bgB / 4);
            $transparentSource = imagecolorallocatealpha($sourceImage, $bgR, $bgG, $bgB, 127);
            imagealphablending($sourceImage, false);
            imagesavealpha($sourceImage, true);
            $minX = $sourceWidth;
            $minY = $sourceHeight;
            $maxX = -1;
            $maxY = -1;
            for ($y = 0; $y < $sourceHeight; $y++) {
                for ($x = 0; $x < $sourceWidth; $x++) {
                    $rgba = imagecolorat($sourceImage, $x, $y);
                    $alpha = ($rgba >> 24) & 0x7F;
                    // Considera prodotto solo i pixel sufficientemente opachi: ombre e aloni WebP non devono ampliare il riquadro.
                    if ($alpha >= 32) continue;
                    if (!$transparentBorder) {
                        $r = ($rgba >> 16) & 0xFF;
                        $g = ($rgba >> 8) & 0xFF;
                        $b = $rgba & 0xFF;
                        if (max(abs($r - $bgR), abs($g - $bgG), abs($b - $bgB)) <= 8) {
                            imagesetpixel($sourceImage, $x, $y, $transparentSource);
                            continue;
                        }
                    }
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
            if ($maxX >= $minX && $maxY >= $minY) {
                $sourceX = $minX;
                $sourceY = $minY;
                $contentWidth = $maxX - $minX + 1;
                $contentHeight = $maxY - $minY + 1;
            }
        }

        $scale = min(980 / $contentWidth, 980 / $contentHeight);
        $targetWidth = max(1, (int)round($contentWidth * $scale));
        $targetHeight = max(1, (int)round($contentHeight * $scale));
        $targetX = (int)floor((1000 - $targetWidth) / 2);
        $targetY = (int)floor((1000 - $targetHeight) / 2);
        imagecopyresampled($canvas, $sourceImage, $targetX, $targetY, $sourceX, $sourceY, $targetWidth, $targetHeight, $contentWidth, $contentHeight);
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