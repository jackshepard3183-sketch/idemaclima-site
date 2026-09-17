<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

trait WarrantyCertificateActionsTrait
{
    public static function generateCertificate(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $id = Validator::int($_POST['id'] ?? 0);
        $pdo = Database::connection();
        self::ensureDetailsTable($pdo);
        self::ensureCertificateTable($pdo);

        $stmt = $pdo->prepare('SELECT wr.*,pm.code,p.name product_name,d.product_type,d.outer_unit,d.combination
            FROM warranty_registrations wr
            JOIN product_models pm ON pm.id=wr.model_id
            JOIN products p ON p.id=pm.product_id
            LEFT JOIN warranty_registration_details d ON d.registration_id=wr.id
            WHERE wr.id=?');
        $stmt->execute([$id]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registration) { http_response_code(404); exit('Registrazione non trovata'); }
        if (!in_array($registration['status'], ['approved','issued'], true)) {
            http_response_code(422); exit('Approva la pratica prima di generare il certificato.');
        }

        $existing = $pdo->prepare('SELECT * FROM warranty_generated_certificates WHERE registration_id=?');
        $existing->execute([$id]);
        $existingCertificate = $existing->fetch(PDO::FETCH_ASSOC) ?: null;

        $stmt = $pdo->prepare('SELECT * FROM warranty_units WHERE registration_id=? ORDER BY id');
        $stmt->execute([$id]); $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $number = $existingCertificate
            ? (string)$existingCertificate['certificate_number']
            : (trim((string)($registration['certificate_number'] ?? '')) !== ''
                ? (string)$registration['certificate_number']
                : self::newCertificateNumber($pdo));
        $fileName = self::certificateFileName($registration);
        $relative = 'warranty-certificates/' . $fileName;
        $directory = dirname(__DIR__, 3) . '/storage/private/warranty-certificates';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            http_response_code(500); exit('Impossibile predisporre l’archivio certificati.');
        }
        $pdf = self::buildCertificatePdf($registration, $units, $number);
        if (file_put_contents($directory . '/' . $fileName, $pdf, LOCK_EX) === false) {
            http_response_code(500); exit('Impossibile salvare il certificato.');
        }
        if ($existingCertificate) {
            if ((string)$existingCertificate['file_path'] !== $relative) self::removePrivatePath((string)$existingCertificate['file_path']);
            $update = $pdo->prepare('UPDATE warranty_generated_certificates SET file_path=?,file_name=?,generated_at=NOW(),sent_at=NULL WHERE registration_id=?');
            $update->execute([$relative,$fileName,$id]);
        } else {
            $insert = $pdo->prepare('INSERT INTO warranty_generated_certificates
                (registration_id,certificate_number,file_path,file_name,generated_at) VALUES (?,?,?,?,NOW())');
            $insert->execute([$id,$number,$relative,$fileName]);
        }
        Audit::log('warranty.certificate.generate','warranty_registration',$id,['certificate_number'=>$number,'file_name'=>$fileName]);
        header('Location: /idemaclima/admin/warranties/registration?id='.$id); exit;
    }

    public static function certificateFile(string $id): void
    {
        AdminAuth::requireLogin();
        self::ensureCertificateTable(Database::connection());
        $stmt = Database::connection()->prepare('SELECT file_path,file_name FROM warranty_generated_certificates WHERE registration_id=?');
        $stmt->execute([(int)$id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || str_contains((string)$row['file_path'],'..')) { http_response_code(404); exit; }
        $root = realpath(dirname(__DIR__,3).'/storage/private');
        $file = realpath(dirname(__DIR__,3).'/storage/private/'.(string)$row['file_path']);
        if (!$root || !$file || !str_starts_with($file,$root.DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit; }
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Disposition: inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_', (string)$row['file_name']).'"');
        header('Content-Length: '.filesize($file)); readfile($file); exit;
    }

    public static function sendCertificate(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $id = Validator::int($_POST['id'] ?? 0);
        $pdo = Database::connection();
        self::ensureCertificateTable($pdo);

        $stmt = $pdo->prepare('SELECT wr.id,wr.status,wr.email,wr.customer_first_name,wr.customer_last_name,
            c.certificate_number,c.file_path,c.file_name,c.sent_at
            FROM warranty_registrations wr
            JOIN warranty_generated_certificates c ON c.registration_id=wr.id
            WHERE wr.id=? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { http_response_code(404); exit('Pratica o certificato non trovato.'); }
        if (!in_array((string)$row['status'], ['approved','issued'], true)) {
            http_response_code(422); exit('Approva la pratica prima di inviare il certificato.');
        }
        if (!empty($row['sent_at'])) {
            http_response_code(409); exit('Il certificato risulta già inviato al cliente.');
        }
        $recipient = trim((string)$row['email']);
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422); exit('L’indirizzo email del cliente non è valido.');
        }

        $root = realpath(dirname(__DIR__,3).'/storage/private');
        $relative = (string)$row['file_path'];
        $file = $relative !== '' && !str_contains($relative,'..')
            ? realpath(dirname(__DIR__,3).'/storage/private/'.$relative)
            : false;
        if (!$root || !$file || !str_starts_with($file,$root.DIRECTORY_SEPARATOR) || !is_file($file)) {
            http_response_code(404); exit('Il file PDF del certificato non è disponibile.');
        }

        if (!self::mailCertificate($recipient, $row, $file)) {
            error_log('IDEMA certificate email not sent for warranty registration '.$id);
            http_response_code(502); exit('Invio non riuscito. La pratica non è stata modificata: riprova oppure contatta il supporto tecnico.');
        }

        $pdo->beginTransaction();
        try {
            $updated = $pdo->prepare('UPDATE warranty_generated_certificates SET sent_at=NOW() WHERE registration_id=? AND sent_at IS NULL');
            $updated->execute([$id]);
            if ($updated->rowCount() !== 1) throw new \RuntimeException('Invio già registrato.');
            $pdo->prepare("UPDATE warranty_registrations SET status='issued',reviewed_at=NOW() WHERE id=?")->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('IDEMA certificate sent but database update failed for warranty registration '.$id.': '.$e->getMessage());
            http_response_code(500); exit('Il messaggio è partito, ma non è stato possibile registrarne l’esito. Contatta il supporto tecnico prima di ripetere l’invio.');
        }

        Audit::log('warranty.certificate.send','warranty_registration',$id,['certificate_number'=>(string)$row['certificate_number']]);
        header('Location: /idemaclima/admin/warranties/registration?id='.$id.'&certificate_sent=1'); exit;
    }

    private static function mailCertificate(string $recipient, array $row, string $file): bool
    {
        $boundary = '=_IDEMA_'.bin2hex(random_bytes(16));
        $number = trim((string)$row['certificate_number']);
        $subject = 'Certificato di estensione garanzia IDEMA - '.$number;
        $customer = trim((string)$row['customer_first_name'].' '.(string)$row['customer_last_name']);
        $body = "Gentile {$customer},\r\n\r\nin allegato trova il certificato di estensione della garanzia IDEMA relativo alla registrazione verificata dal nostro reparto assistenza.\r\n\r\nCordiali saluti\r\nIdema Clima S.r.l.";
        $safeName = preg_replace('/[^A-Za-z0-9._-]/','_', (string)$row['file_name']) ?: 'Certificato-Garanzia-IDEMA.pdf';
        $attachment = chunk_split(base64_encode((string)file_get_contents($file)));
        $headers = [
            'From: Idema Clima Srl <no-reply@rappresentanzeguanzirolisas.it>',
            'Reply-To: commerciale.tre@idemaclima.it',
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="'.$boundary.'"',
            'X-Mailer: IDEMA Website',
        ];
        $message = '--'.$boundary."\r\n".
            "Content-Type: text/plain; charset=UTF-8\r\n".
            "Content-Transfer-Encoding: 8bit\r\n\r\n".$body."\r\n\r\n".
            '--'.$boundary."\r\n".
            'Content-Type: application/pdf; name="'.$safeName."\"\r\n".
            "Content-Transfer-Encoding: base64\r\n".
            'Content-Disposition: attachment; filename="'.$safeName."\"\r\n\r\n".
            $attachment."\r\n--".$boundary."--\r\n";
        return @mail($recipient, $subject, $message, implode("\r\n", $headers));
    }

    private static function removePrivatePath(string $relative): void
    {
        if ($relative === '' || str_contains($relative, '..')) return;
        $root = realpath(dirname(__DIR__,3).'/storage/private');
        $file = realpath(dirname(__DIR__,3).'/storage/private/'.$relative);
        if ($root && $file && str_starts_with($file,$root.DIRECTORY_SEPARATOR) && is_file($file)) @unlink($file);
    }


    private static function ensureCertificateTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS warranty_generated_certificates (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            registration_id BIGINT UNSIGNED NOT NULL,
            certificate_number VARCHAR(40) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            generated_at DATETIME NOT NULL,
            sent_at DATETIME NULL,
            UNIQUE KEY uq_warranty_generated_registration (registration_id),
            UNIQUE KEY uq_warranty_generated_number (certificate_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    private static function newCertificateNumber(PDO $pdo): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i=0;$i<8;$i++) $code .= $alphabet[random_int(0,strlen($alphabet)-1)];
            $number = 'IDM-' . $code;
            $stmt = $pdo->prepare('SELECT 1 FROM warranty_generated_certificates WHERE certificate_number=?');
            $stmt->execute([$number]);
        } while ($stmt->fetchColumn());
        return $number;
    }

    private static function certificateFileName(array $r): string
    {
        $type = ($r['product_type'] ?? '') === 'multi' ? 'Multi-Split' : 'Mono-Split';
        if ($type === 'Multi-Split') {
            $system = (string)($r['outer_unit'] ?? $r['combination'] ?? $r['code']);
        } else {
            $model = trim((string)($r['combination'] ?? $r['code']));
            if (str_contains($model, '+')) {
                $system = preg_replace('/\\s*\\+\\s*/', '_', $model) ?? $model;
            } elseif (preg_match('/^(.+?)-R32$/i', $model, $match)) {
                $system = $match[1] . 'UI-R32_' . $match[1] . 'UE-R32';
            } else {
                $system = $model;
            }
        }
        $system = str_replace('+','_',$system);
        $system = trim((string)preg_replace('/[^A-Za-z0-9._-]+/','-',$system),'-');
        return 'Estensione-Garanzia-IDEMA-' . $type . '-' . $system . '.pdf';
    }

    /* PDF rendering lives in WarrantyCertificatePdf so deployments do not depend
       on transferring one oversized controller file. */
}
