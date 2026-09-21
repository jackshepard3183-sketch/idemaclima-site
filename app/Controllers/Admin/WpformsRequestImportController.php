<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;
use RuntimeException;
use Throwable;

final class WpformsRequestImportController
{
    public static function index(string $kind): void
    {
        AdminAuth::requireLogin();
        self::assertKind($kind);
        $title = $kind === 'contacts' ? 'Importazione Contatti WPForms' : 'Importazione EasyTool WPForms';
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/wpforms_request_import.php';
    }

    public static function run(string $kind): void
    {
        AdminAuth::requireLogin();
        self::assertKind($kind);
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Sessione non valida');
        }
        if (!isset($_FILES['manifest']) || !is_uploaded_file((string)($_FILES['manifest']['tmp_name'] ?? ''))) {
            self::respond(['imported' => 0, 'skipped' => 0, 'errors' => ['Seleziona un file JSON.']]);
            return;
        }
        if ((int)($_FILES['manifest']['size'] ?? 0) > 2097152) {
            self::respond(['imported' => 0, 'skipped' => 0, 'errors' => ['Il file supera 2 MB.']]);
            return;
        }
        $payload = json_decode((string)file_get_contents((string)$_FILES['manifest']['tmp_name']), true);
        if (!is_array($payload) || !is_array($payload['items'] ?? null)) {
            self::respond(['imported' => 0, 'skipped' => 0, 'errors' => ['Manifest non valido.']]);
            return;
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        $pdo = Database::connection();
        $done = 0;
        $skipped = 0;
        $errors = [];
        foreach ($payload['items'] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sourceId = (int)($item['source_id'] ?? 0);
            $attachmentPath = null;
            try {
                if ($sourceId < 1) {
                    throw new RuntimeException('ID WPForms mancante');
                }
                $table = $kind === 'contacts' ? 'contact_submissions' : 'incentive_requests';
                $check = $pdo->prepare('SELECT id FROM ' . $table . ' WHERE source_wpforms_id=? LIMIT 1');
                $check->execute([$sourceId]);
                if ($check->fetchColumn()) {
                    $skipped++;
                    continue;
                }
                $createdAt = self::normalizeWpformsDate((string)($item['source_created_at'] ?? ''));
                $pdo->beginTransaction();
                if ($kind === 'contacts') {
                    $attachment = self::copyContactAttachment((string)($item['attachment_url'] ?? ''), $sourceId);
                    $attachmentPath = $attachment['path'];
                    $stmt = $pdo->prepare(
                        'INSERT INTO contact_submissions '
                        . '(source_wpforms_id,first_name,last_name,region,province,city,postal_code,email,phone,subject,message,attachment_path,attachment_name,attachment_mime,privacy_accepted_at,status,import_review_warning,imported_at,created_at) '
                        . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'new\',?,NOW(),?)'
                    );
                    $stmt->execute([
                        $sourceId,
                        self::upper((string)($item['first_name'] ?? '')),
                        self::upper((string)($item['last_name'] ?? '')),
                        self::upper((string)($item['region'] ?? '')),
                        Validator::provinceCode($item['province'] ?? ''),
                        self::upper((string)($item['city'] ?? '')),
                        self::upper((string)($item['postal_code'] ?? '')),
                        strtolower(trim((string)($item['email'] ?? ''))),
                        self::upper((string)($item['phone'] ?? '')),
                        self::upper((string)($item['subject'] ?? '')),
                        self::upperMultiline((string)($item['message'] ?? '')),
                        $attachment['path'], $attachment['name'], $attachment['mime'],
                        $createdAt,
                        trim((string)($item['review_warning'] ?? '')) ?: null,
                        $createdAt,
                    ]);
                } else {
                    $stmt = $pdo->prepare(
                        'INSERT INTO incentive_requests '
                        . '(source_wpforms_id,first_name,last_name,company,region,province,city,postal_code,phone,email,professional_role,status,privacy_accepted_at,import_review_warning,imported_at,created_at) '
                        . 'VALUES (?,?,?,?,?,?,?,?,?,?,?,\'new\',?,?,NOW(),?)'
                    );
                    $stmt->execute([
                        $sourceId,
                        self::upper((string)($item['first_name'] ?? '')),
                        self::upper((string)($item['last_name'] ?? '')),
                        Validator::companyName($item['company'] ?? '') ?: null,
                        self::upper((string)($item['region'] ?? '')),
                        Validator::provinceCode($item['province'] ?? ''),
                        self::upper((string)($item['city'] ?? '')),
                        self::upper((string)($item['postal_code'] ?? '')),
                        self::upper((string)($item['phone'] ?? '')),
                        strtolower(trim((string)($item['email'] ?? ''))),
                        self::upper((string)($item['professional_role'] ?? 'NON INDICATO - IMPORT STORICO')),
                        $createdAt,
                        trim((string)($item['review_warning'] ?? '')) ?: null,
                        $createdAt,
                    ]);
                }
                $pdo->commit();
                $done++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                self::removeAttachment($attachmentPath);
                $errors[] = '#' . $sourceId . ': ' . $e->getMessage();
            }
        }
        self::respond(['imported' => $done, 'skipped' => $skipped, 'errors' => $errors]);
    }

    private static function assertKind(string $kind): void
    {
        if (!in_array($kind, ['contacts', 'incentives'], true)) {
            http_response_code(404);
            exit('Importatore non trovato');
        }
    }

    private static function normalizeWpformsDate(string $value): string
    {
        $months = ['GENNAIO'=>1,'FEBBRAIO'=>2,'MARZO'=>3,'APRILE'=>4,'MAGGIO'=>5,'GIUGNO'=>6,'LUGLIO'=>7,'AGOSTO'=>8,'SETTEMBRE'=>9,'OTTOBRE'=>10,'NOVEMBRE'=>11,'DICEMBRE'=>12];
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/', trim($value), $m)) {
            return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6] ?? 0);
        }
        if (!preg_match('/^(\d{1,2})\s+([^\s]+)\s+(\d{4})\s+(\d{1,2}):(\d{2})$/u', trim($value), $m)) {
            throw new RuntimeException('Data WPForms non valida');
        }
        $month = $months[mb_strtoupper($m[2], 'UTF-8')] ?? 0;
        if ($month < 1 || !checkdate($month, (int)$m[1], (int)$m[3])) {
            throw new RuntimeException('Data WPForms non valida');
        }
        return sprintf('%04d-%02d-%02d %02d:%02d:00', $m[3], $month, $m[1], $m[4], $m[5]);
    }

    private static function upper(string $value): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $value) ?? $value), 'UTF-8');
    }

    private static function upperMultiline(string $value): string
    {
        return mb_strtoupper(trim(str_replace(["\r\n", "\r"], "\n", $value)), 'UTF-8');
    }

    private static function copyContactAttachment(string $url, int $sourceId): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['path' => null, 'name' => null, 'mime' => null];
        }
        if (!preg_match('#^https?://www\.idemaclima\.it/wp-content/uploads/wpforms/#i', $url)) {
            throw new RuntimeException('URL allegato non consentito');
        }
        $url = preg_replace('#^http://#i', 'https://', $url) ?? $url;
        $sourceName = rawurldecode(basename((string)parse_url($url, PHP_URL_PATH)));
        $ext = strtolower((string)pathinfo($sourceName, PATHINFO_EXTENSION));
        $mimes = ['pdf'=>'application/pdf','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','doc'=>'application/msword','docx'=>'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        if (!isset($mimes[$ext])) {
            throw new RuntimeException('Formato allegato non valido');
        }
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL non disponibile sul server');
        }
        $relative = 'contacts/imported/wpforms-' . $sourceId . '-' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $absolute = dirname(__DIR__, 3) . '/storage/private/' . $relative;
        if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0700, true) && !is_dir(dirname($absolute))) {
            throw new RuntimeException('Cartella privata non disponibile');
        }
        $temporary = $absolute . '.part-' . bin2hex(random_bytes(4));
        $handle = fopen($temporary, 'wb');
        if ($handle === false) {
            throw new RuntimeException('File temporaneo non disponibile');
        }
        $tooLarge = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; IDEMA-WPForms-Migration/1.0)',
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static function ($curl, $total, $downloaded) use (&$tooLarge): int {
                if ($total > 15728640 || $downloaded > 15728640) { $tooLarge = true; return 1; }
                return 0;
            },
        ]);
        $ok = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch);
        curl_close($ch); fclose($handle);
        if ($ok !== true || $status < 200 || $status >= 300) {
            @unlink($temporary);
            throw new RuntimeException($tooLarge ? 'Allegato superiore a 15 MB' : 'Download allegato non riuscito' . ($error !== '' ? ': ' . $error : ' (HTTP ' . $status . ')'));
        }
        $size = filesize($temporary);
        if ($size === false || $size < 1 || $size > 15728640 || !rename($temporary, $absolute)) {
            @unlink($temporary);
            throw new RuntimeException('Salvataggio allegato non riuscito');
        }
        @chmod($absolute, 0600);
        return ['path' => $relative, 'name' => substr($sourceName, 0, 190), 'mime' => $mimes[$ext]];
    }

    private static function removeAttachment(?string $relative): void
    {
        if ($relative === null || !str_starts_with($relative, 'contacts/imported/')) return;
        $path = dirname(__DIR__, 3) . '/storage/private/' . $relative;
        if (is_file($path)) @unlink($path);
    }

    private static function respond(array $payload): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
}
