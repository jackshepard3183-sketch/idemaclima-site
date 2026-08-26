<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class PrivateUpload
{
    private const WARRANTY_MAX_BYTES = 15 * 1024 * 1024;
    private const CONTACT_MAX_BYTES = 10 * 1024 * 1024;

    /** @return array{path:string,filename:string,original_name:string,mime:string,size:int}|null */
    public static function warrantyDocument(string $field, array &$errors): ?array
    {
        return self::store($field, 'warranty', self::WARRANTY_MAX_BYTES, [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ], $errors);
    }

    /** @return array{path:string,filename:string,original_name:string,mime:string,size:int}|null */
    public static function contactAttachment(string $field, array &$errors): ?array
    {
        return self::store($field, 'contacts', self::CONTACT_MAX_BYTES, [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
        ], $errors);
    }

    /** @param array<string,string> $allowed */
    private static function store(string $field, string $bucket, int $maxBytes, array $allowed, array &$errors): ?array
    {
        if (!isset($_FILES[$field]) || !is_array($_FILES[$field])) return null;
        $file = $_FILES[$field];
        $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) return null;
        if ($error !== UPLOAD_ERR_OK) { $errors[] = 'Caricamento file non riuscito.'; return null; }

        $tmp = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $original = basename((string)($file['name'] ?? 'file'));
        if ($tmp === '' || !is_uploaded_file($tmp) || $size < 1) { $errors[] = 'File caricato non valido.'; return null; }
        if ($size > $maxBytes) { $errors[] = sprintf('File troppo grande. Dimensione massima: %d MB.', (int)($maxBytes / 1024 / 1024)); return null; }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($tmp);
        $ext = $allowed[$mime] ?? null;
        if ($ext === null) { $errors[] = 'Formato file non consentito.'; return null; }

        if ($mime === 'application/pdf') {
            $h = @fopen($tmp, 'rb'); $sig = $h ? (string)fread($h, 5) : ''; if ($h) fclose($h);
            if ($sig !== '%PDF-') { $errors[] = 'PDF non valido.'; return null; }
        } elseif (str_starts_with($mime, 'image/') && @getimagesize($tmp) === false) {
            $errors[] = 'Immagine non valida.'; return null;
        } elseif ($ext === 'zip') {
            $h = @fopen($tmp, 'rb'); $sig = $h ? (string)fread($h, 4) : ''; if ($h) fclose($h);
            if (!in_array($sig, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)) { $errors[] = 'Archivio ZIP non valido.'; return null; }
        }

        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $relative = $bucket . '/' . date('Y') . '/' . date('m') . '/' . $filename;
        $absolute = dirname(__DIR__, 2) . '/storage/private/' . $relative;
        $dir = dirname($absolute);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('Impossibile creare la cartella privata.');
        if (!move_uploaded_file($tmp, $absolute)) throw new RuntimeException('Impossibile salvare il file privato.');
        @chmod($absolute, 0600);

        return ['path'=>$relative,'filename'=>$filename,'original_name'=>$original,'mime'=>$mime,'size'=>$size];
    }

    public static function remove(?string $relativePath): void
    {
        if (!$relativePath || str_contains($relativePath, '..')) return;
        $root = realpath(dirname(__DIR__, 2) . '/storage/private');
        if ($root === false) return;
        $candidate = dirname(__DIR__, 2) . '/storage/private/' . ltrim($relativePath, '/');
        $real = realpath($candidate);
        if ($real !== false && str_starts_with($real, $root . DIRECTORY_SEPARATOR) && is_file($real)) @unlink($real);
    }
}
