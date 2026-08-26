<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class PrivateUpload
{
    private const MAX_BYTES = 15 * 1024 * 1024;

    /** @return array{path:string,filename:string,mime:string,size:int}|null */
    public static function warrantyDocument(string $field, array &$errors): ?array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
        $file = $_FILES[$field];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) return null;
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = 'Caricamento file non riuscito.';
            return null;
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1) {
            $errors[] = 'File caricato non valido.';
            return null;
        }
        if ($size > self::MAX_BYTES) {
            $errors[] = 'File troppo grande. Dimensione massima: 15 MB.';
            return null;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $allowed = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];
        $ext = $allowed[$mime] ?? null;
        if ($ext === null) {
            $errors[] = 'Formato non consentito. Usa PDF, JPG o PNG.';
            return null;
        }

        if ($mime === 'application/pdf') {
            $h = @fopen($tmp, 'rb');
            $sig = $h ? (string)fread($h, 5) : '';
            if ($h) fclose($h);
            if ($sig !== '%PDF-') {
                $errors[] = 'PDF non valido.';
                return null;
            }
        } elseif (@getimagesize($tmp) === false) {
            $errors[] = 'Immagine non valida.';
            return null;
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $relative = 'warranty/' . date('Y') . '/' . date('m') . '/' . $filename;
        $absolute = dirname(__DIR__, 2) . '/storage/private/' . $relative;
        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Impossibile creare la cartella privata.');
        }
        if (!move_uploaded_file($tmp, $absolute)) {
            throw new RuntimeException('Impossibile salvare il file privato.');
        }
        @chmod($absolute, 0600);

        return ['path' => $relative, 'filename' => $filename, 'mime' => $mime, 'size' => $size];
    }

    public static function remove(?string $relativePath): void
    {
        if (!$relativePath || str_contains($relativePath, '..')) return;
        $root = realpath(dirname(__DIR__, 2) . '/storage/private');
        if ($root === false) return;
        $candidate = dirname(__DIR__, 2) . '/storage/private/' . ltrim($relativePath, '/');
        $real = realpath($candidate);
        if ($real !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real)) {
            @unlink($real);
        }
    }
}
