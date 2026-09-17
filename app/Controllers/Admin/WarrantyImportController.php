<?php
declare(strict_types=1);








namespace App\Controllers\Admin;








use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use App\Services\WarrantyService;
use PDO;
use RuntimeException;
use Throwable;








final class WarrantyImportController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $title = 'Importazione garanzie WPForms';
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_import.php';
    }








    public static function run(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        if (!isset($_FILES['manifest']) || !is_uploaded_file((string)($_FILES['manifest']['tmp_name'] ?? ''))) { http_response_code(422); exit('Seleziona il file JSON.'); }
        $raw = file_get_contents((string)$_FILES['manifest']['tmp_name']);
        $payload = json_decode((string)$raw, true);
        if (!is_array($payload) || !is_array($payload['items'] ?? null)) { http_response_code(422); exit('Manifest non valido.'); }
        $done = 0; $skipped = 0; $errors = [];
        try {
            if (function_exists('set_time_limit')) @set_time_limit(0);
            $pdo = Database::connection();
            $columns = array_column($pdo->query('SHOW COLUMNS FROM warranty_registrations')->fetchAll(PDO::FETCH_ASSOC), 'Field');
        } catch (Throwable $e) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['imported'=>0, 'skipped'=>0, 'errors'=>['Preparazione importazione: ' . $e->getMessage()]], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            return;
        }
        foreach ($payload['items'] as $item) {
            if (!is_array($item)) continue;
            $sourceId = (int)($item['source_id'] ?? 0);
            $invoicePath = null;
            $fgasPath = null;
            try {
                if ($sourceId < 1) throw new RuntimeException('ID WPForms mancante');
                $sourceCreatedAt = self::normalizeWpformsDate((string)($item['source_created_at'] ?? ''));
                $check = $pdo->prepare('SELECT id FROM warranty_registrations WHERE source_wpforms_id=? LIMIT 1');
                $check->execute([$sourceId]);
                if ($check->fetchColumn()) { $skipped++; continue; }
                $modelId = WarrantyService::modelIdFromCombination($pdo, (string)($item['combination'] ?? ''));
                if ($modelId < 1) throw new RuntimeException('Modello non trovato');
                $invoicePath = self::copyDocument((string)($item['invoice_url'] ?? ''), 'invoice', $sourceId);
                $fgasPath = self::copyDocument((string)($item['fgas_url'] ?? ''), 'fgas', $sourceId);
                $pdo->beginTransaction();
                $data = [
                    'source_wpforms_id'=>$sourceId,
                    'certificate_number'=>self::newCode($pdo),
                    'model_id'=>$modelId,
                    'customer_first_name'=>self::normalizeName((string)($item['first_name'] ?? '')),
                    'customer_last_name'=>self::normalizeName((string)($item['last_name'] ?? '')),
                    'fiscal_code'=>strtoupper(trim((string)($item['fiscal_code'] ?? ''))),
                    'email'=>strtolower(trim((string)($item['email'] ?? ''))),
                    'phone'=>trim((string)($item['phone'] ?? '')) ?: null,
                    'address'=>trim((string)($item['address'] ?? '')),
                    'postal_code'=>strtoupper(trim((string)($item['postal_code'] ?? ''))),
                    'city'=>trim((string)($item['city'] ?? '')),
                    'province'=>Validator::provinceCode($item['province'] ?? ''),
                    'region'=>strtoupper(trim((string)($item['region'] ?? ''))),
                    'invoice_date'=>(string)($item['invoice_date'] ?? ''),
                    'invoice_file'=>$invoicePath,
                    'fgas_file'=>$fgasPath,
                    'privacy_accepted_at'=>$sourceCreatedAt,
                    'status'=>'pending',
                    'warranty_years'=>10,
                    'extension_formula'=>null,
                    'admin_notes'=>null,
                    'reviewed_at'=>null,
                    'invoice_required_snapshot'=>1,
                    'fgas_required_snapshot'=>1,
                    'import_review_warning'=>trim((string)($item['review_warning'] ?? '')) ?: null,
                    'imported_at'=>date('Y-m-d H:i:s'),
                    'created_at'=>$sourceCreatedAt,
                ];
                $data = array_intersect_key($data, array_flip($columns));
                $names = array_keys($data);
                $sql = 'INSERT INTO warranty_registrations (' . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0,count($names),'?')) . ')';
                $pdo->prepare($sql)->execute(array_values($data));
                $registrationId = (int)$pdo->lastInsertId();
                $unit = $pdo->prepare('INSERT INTO warranty_units (registration_id,model_id,unit_type,serial_number) VALUES (?,?,?,?)');
                $unit->execute([$registrationId,$modelId,'outdoor',strtoupper(trim((string)$item['outdoor_serial']))]);
                foreach (($item['indoor_serials'] ?? []) as $serial) {
                    $serial = strtoupper(trim((string)$serial));
                    if ($serial !== '') $unit->execute([$registrationId,null,'indoor',$serial]);
                }
                $pdo->prepare('INSERT INTO warranty_registration_details (registration_id,product_type,outer_unit,combination) VALUES (?,?,?,?)')
                    ->execute([$registrationId,(string)$item['product_type'],strtoupper((string)$item['model_code']),strtoupper((string)$item['combination'])]);
                $pdo->commit();
                $done++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                self::removeImportedDocument($invoicePath);
                self::removeImportedDocument($fgasPath);
                $errors[] = '#' . $sourceId . ': ' . $e->getMessage();
            }
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['imported'=>$done,'skipped'=>$skipped,'errors'=>$errors], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    }


    private static function copyDocument(string $url, string $kind, int $sourceId): string
    {
        if (!preg_match('#^https?://www\.idemaclima\.it/wp-content/uploads/wpforms/#i', $url)) throw new RuntimeException('URL documento non consentito');
        $url = preg_replace('#^http://#i', 'https://', $url) ?? $url;
        $ext = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) throw new RuntimeException('Formato ' . $kind . ' non valido');
        if (!function_exists('curl_init')) throw new RuntimeException('cURL non disponibile sul server');

        $name = 'wpforms-' . $sourceId . '-' . $kind . '-' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $relative = 'warranty/imported/' . $name;
        $absolute = dirname(__DIR__, 3) . '/storage/private/' . $relative;
        if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0700, true) && !is_dir(dirname($absolute))) throw new RuntimeException('Cartella privata non disponibile');

        $temporary = $absolute . '.part-' . bin2hex(random_bytes(4));
        $handle = fopen($temporary, 'wb');
        if ($handle === false) throw new RuntimeException('File temporaneo ' . $kind . ' non disponibile');

        $tooLarge = false;
        $ch = curl_init($url);
        if ($ch === false) {
            fclose($handle);
            @unlink($temporary);
            throw new RuntimeException('Inizializzazione download ' . $kind . ' non riuscita');
        }
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; IDEMA-WPForms-Migration/1.0)',
            CURLOPT_HTTPHEADER => ['Accept: application/pdf,image/jpeg,image/png,*/*'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static function ($curl, $downloadTotal, $downloaded) use (&$tooLarge): int {
                if ($downloadTotal > 15728640 || $downloaded > 15728640) {
                    $tooLarge = true;
                    return 1;
                }
                return 0;
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($ok !== true || $status < 200 || $status >= 300) {
            @unlink($temporary);
            if ($tooLarge) throw new RuntimeException('Documento ' . $kind . ' superiore a 15 MB');
            $detail = $error !== '' ? ': ' . $error : ' (HTTP ' . $status . ')';
            throw new RuntimeException('Download ' . $kind . ' non riuscito' . $detail);
        }

        $size = filesize($temporary);
        if ($size === false || $size < 5 || $size > 15728640) {
            @unlink($temporary);
            throw new RuntimeException('Download ' . $kind . ' non riuscito');
        }
        if ($ext === 'pdf' && file_get_contents($temporary, false, null, 0, 5) !== '%PDF-') {
            @unlink($temporary);
            throw new RuntimeException('PDF ' . $kind . ' non valido');
        }
        if (!rename($temporary, $absolute)) {
            @unlink($temporary);
            throw new RuntimeException('Salvataggio ' . $kind . ' non riuscito');
        }
        @chmod($absolute, 0600);
        return $relative;
    }



    private static function normalizeExistingImportedNames(PDO $pdo): void
    {
        $rows = $pdo->query(
            'SELECT id, customer_first_name, customer_last_name, province
             FROM warranty_registrations
             WHERE source_wpforms_id IS NOT NULL'
        )->fetchAll(PDO::FETCH_ASSOC);

        $update = $pdo->prepare(
            'UPDATE warranty_registrations
             SET customer_first_name=?, customer_last_name=?, province=?
             WHERE id=?'
        );

        foreach ($rows as $row) {
            $firstName = self::normalizeName((string)($row['customer_first_name'] ?? ''));
            $lastName = self::normalizeName((string)($row['customer_last_name'] ?? ''));
            $province = Validator::provinceCode($row['province'] ?? '');
            if ($firstName === (string)$row['customer_first_name']
                && $lastName === (string)$row['customer_last_name']
                && $province === (string)$row['province']) {
                continue;
            }
            $update->execute([$firstName, $lastName, $province, (int)$row['id']]);
        }
    }

    private static function normalizeName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        if ($value === '') return '';

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }

    private static function normalizeWpformsDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) return $value;
        if (!preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\s+(\d{1,2}):(\d{2})$/u', $value, $m)) {
            throw new RuntimeException('Data WPForms non valida: ' . $value);
        }
        $months = [
            'gennaio'=>1,'febbraio'=>2,'marzo'=>3,'aprile'=>4,'maggio'=>5,'giugno'=>6,
            'luglio'=>7,'agosto'=>8,'settembre'=>9,'ottobre'=>10,'novembre'=>11,'dicembre'=>12,
        ];
        $month = $months[strtolower($m[2])] ?? 0;
        $day = (int)$m[1]; $year = (int)$m[3]; $hour = (int)$m[4]; $minute = (int)$m[5];
        if ($month < 1 || !checkdate($month, $day, $year) || $hour > 23 || $minute > 59) {
            throw new RuntimeException('Data WPForms non valida: ' . $value);
        }
        return sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute);
    }


    private static function cleanupOrphanedImports(PDO $pdo): void
    {
        $used = [];
        $rows = $pdo->query('SELECT invoice_file, fgas_file FROM warranty_registrations')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            foreach (['invoice_file','fgas_file'] as $field) {
                $path = trim((string)($row[$field] ?? ''));
                if ($path !== '') $used[$path] = true;
            }
        }
        $directory = dirname(__DIR__, 3) . '/storage/private/warranty/imported';
        foreach (glob($directory . '/wpforms-*') ?: [] as $file) {
            $relative = 'warranty/imported/' . basename($file);
            if (is_file($file) && !isset($used[$relative])) @unlink($file);
        }
    }


    private static function removeImportedDocument(?string $relative): void
    {
        if ($relative === null || !str_starts_with($relative, 'warranty/imported/wpforms-')) return;
        $absolute = dirname(__DIR__, 3) . '/storage/private/' . $relative;
        if (is_file($absolute)) @unlink($absolute);
    }

    private static function newCode(PDO $pdo): string
    {
        $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code=''; for($i=0;$i<8;$i++) $code.=$alphabet[random_int(0,strlen($alphabet)-1)];
            $number='IDM-'.$code;
            $q=$pdo->prepare('SELECT 1 FROM warranty_registrations WHERE certificate_number=? LIMIT 1'); $q->execute([$number]);
        } while ($q->fetchColumn());
        return $number;
    }
}
