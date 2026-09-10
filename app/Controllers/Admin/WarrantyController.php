<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use App\Services\WarrantyService;
use PDO;

final class WarrantyController
{
    use WarrantyCertificatePdfTrait;
    public static function registrations(): void
    {
        AdminAuth::requireLogin();
        $sql = 'SELECT wr.id,wr.customer_first_name,wr.customer_last_name,wr.email,wr.invoice_date,wr.status,wr.warranty_years,wr.created_at,pm.code,p.name product_name
                FROM warranty_registrations wr JOIN product_models pm ON pm.id=wr.model_id JOIN products p ON p.id=pm.product_id
                ORDER BY wr.created_at DESC,wr.id DESC';
        $registrations = Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $title = 'Registrazioni garanzia'; $user = AdminAuth::user(); $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_registrations.php';
    }

    public static function registration(): void
    {
        AdminAuth::requireLogin();
        $id = Validator::int($_GET['id'] ?? 0);
        $pdo = Database::connection();
        self::ensureDetailsTable($pdo);
        self::ensureCertificateTable($pdo);
        $stmt = $pdo->prepare('SELECT wr.*,pm.code,p.name product_name FROM warranty_registrations wr JOIN product_models pm ON pm.id=wr.model_id JOIN products p ON p.id=pm.product_id WHERE wr.id=?');
        $stmt->execute([$id]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registration) { http_response_code(404); exit('Registrazione non trovata'); }
        $stmt = $pdo->prepare('SELECT * FROM warranty_units WHERE registration_id=? ORDER BY id');
        $stmt->execute([$id]); $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT * FROM warranty_registration_details WHERE registration_id=?');
        $stmt->execute([$id]); $details = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $stmt = $pdo->prepare('SELECT * FROM warranty_generated_certificates WHERE registration_id=?');
        $stmt->execute([$id]); $certificate = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $models = $pdo->query('SELECT pm.id,pm.code,p.name product_name FROM product_models pm JOIN products p ON p.id=pm.product_id WHERE pm.published=1 ORDER BY p.name,pm.code')->fetchAll(PDO::FETCH_ASSOC);
        $title = 'Garanzia #' . $id; $user = AdminAuth::user(); $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_registration.php';
    }

    public static function updateStatus(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $id = Validator::int($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'pending');
        if (!in_array($status, ['pending','approved','rejected','issued'], true)) { http_response_code(422); exit('Stato non valido'); }
        $notes = trim((string)($_POST['admin_notes'] ?? ''));
        if (mb_strlen($notes) > 10000) { http_response_code(422); exit('Note troppo lunghe'); }

        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT status FROM warranty_registrations WHERE id=? LIMIT 1');
        $exists->execute([$id]);
        $previous = $exists->fetchColumn();
        if ($previous === false) { http_response_code(404); exit('Registrazione non trovata'); }

        if ($status === 'issued') {
            self::ensureCertificateTable($pdo);
            $certificate = $pdo->prepare('SELECT 1 FROM warranty_generated_certificates WHERE registration_id=? LIMIT 1');
            $certificate->execute([$id]);
            if (!$certificate->fetchColumn()) {
                http_response_code(422);
                exit('Non puoi impostare “Emessa” finché non esiste il certificato PDF associato.');
            }
        }

        $reviewedAt = $status === 'pending' ? null : date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('UPDATE warranty_registrations SET status=?,admin_notes=?,reviewed_at=? WHERE id=?');
        $stmt->execute([$status, $notes ?: null, $reviewedAt, $id]);
        Audit::log('warranty.status', 'warranty_registration', $id, ['from'=>$previous,'to'=>$status]);
        header('Location: /admin/warranties/registration?id=' . $id); exit;
    }

    public static function updateRegistration(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $id = Validator::int($_POST['id'] ?? 0);
        $modelId = Validator::int($_POST['model_id'] ?? 0);
        $required = ['customer_first_name','customer_last_name','fiscal_code','email','address','postal_code','city','province','region','invoice_date','combination'];
        $data = [];
        foreach ($required as $field) {
            $data[$field] = trim((string)($_POST[$field] ?? ''));
            if ($data[$field] === '') { http_response_code(422); exit('Compila tutti i campi obbligatori.'); }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) { http_response_code(422); exit('Email non valida.'); }
        if (!WarrantyService::isIsoDate($data['invoice_date'])) { http_response_code(422); exit('Data fattura non valida.'); }
        $phone = trim((string)($_POST['phone'] ?? ''));
        $productType = (string)($_POST['product_type'] ?? 'mono');
        if (!in_array($productType, ['mono','multi'], true)) $productType = 'mono';
        $outerUnit = trim((string)($_POST['outer_unit'] ?? ''));
        $serials = is_array($_POST['unit_serials'] ?? null) ? $_POST['unit_serials'] : [];

        $pdo = Database::connection();
        self::ensureDetailsTable($pdo); self::ensureCertificateTable($pdo);
        $stmt = $pdo->prepare('SELECT status FROM warranty_registrations WHERE id=?');
        $stmt->execute([$id]); $currentStatus = $stmt->fetchColumn();
        if ($currentStatus === false) { http_response_code(404); exit('Registrazione non trovata'); }
        $stmt = $pdo->prepare('SELECT 1 FROM product_models WHERE id=? AND published=1');
        $stmt->execute([$modelId]);
        if (!$stmt->fetchColumn()) { http_response_code(422); exit('Modello non valido.'); }
        $stmt = $pdo->prepare('SELECT file_path FROM warranty_generated_certificates WHERE registration_id=?');
        $stmt->execute([$id]); $certificatePath = $stmt->fetchColumn() ?: null;

        $pdo->beginTransaction();
        try {
            $newStatus = $currentStatus === 'issued' ? 'approved' : $currentStatus;
            $stmt = $pdo->prepare('UPDATE warranty_registrations SET model_id=?,customer_first_name=?,customer_last_name=?,fiscal_code=?,email=?,phone=?,address=?,postal_code=?,city=?,province=?,region=?,invoice_date=?,status=? WHERE id=?');
            $stmt->execute([$modelId,$data['customer_first_name'],$data['customer_last_name'],$data['fiscal_code'],$data['email'],$phone ?: null,$data['address'],$data['postal_code'],$data['city'],strtoupper($data['province']),$data['region'],$data['invoice_date'],$newStatus,$id]);
            $updateSerial = $pdo->prepare('UPDATE warranty_units SET serial_number=? WHERE id=? AND registration_id=?');
            foreach ($serials as $unitId => $serial) {
                $serial = trim((string)$serial);
                if ($serial === '') throw new \RuntimeException('I numeri di serie non possono essere vuoti.');
                $updateSerial->execute([$serial,(int)$unitId,$id]);
            }
            $stmt = $pdo->prepare('INSERT INTO warranty_registration_details (registration_id,product_type,outer_unit,combination) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE product_type=VALUES(product_type),outer_unit=VALUES(outer_unit),combination=VALUES(combination)');
            $stmt->execute([$id,$productType,$outerUnit ?: null,$data['combination']]);
            $pdo->prepare('DELETE FROM warranty_generated_certificates WHERE registration_id=?')->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(422); exit($e->getMessage());
        }
        if ($certificatePath) self::removePrivatePath((string)$certificatePath);
        Audit::log('warranty.registration.update','warranty_registration',$id,['model_id'=>$modelId]);
        header('Location: /idemaclima/admin/warranties/registration?id='.$id); exit;
    }

    public static function deleteRegistration(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $id = Validator::int($_POST['id'] ?? 0);
        $pdo = Database::connection();
        self::ensureDetailsTable($pdo); self::ensureCertificateTable($pdo);
        $stmt = $pdo->prepare('SELECT invoice_file,fgas_file FROM warranty_registrations WHERE id=?');
        $stmt->execute([$id]); $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registration) { http_response_code(404); exit('Registrazione non trovata'); }
        $stmt = $pdo->prepare('SELECT file_path FROM warranty_generated_certificates WHERE registration_id=?');
        $stmt->execute([$id]); $certificatePath = $stmt->fetchColumn() ?: null;
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM warranty_generated_certificates WHERE registration_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM warranty_registration_details WHERE registration_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM warranty_units WHERE registration_id=?')->execute([$id]);
            $pdo->prepare('DELETE FROM warranty_registrations WHERE id=?')->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500); exit('Impossibile eliminare la richiesta.');
        }
        foreach ([$registration['invoice_file'] ?? null,$registration['fgas_file'] ?? null,$certificatePath] as $path) {
            if ($path) self::removePrivatePath((string)$path);
        }
        Audit::log('warranty.registration.delete','warranty_registration',$id,[]);
        header('Location: /idemaclima/admin/warranties'); exit;
    }

    public static function rules(): void
    {
        AdminAuth::requireLogin();
        $sql = 'SELECT wr.*,p.name product_name,pm.code model_code FROM warranty_rules wr LEFT JOIN products p ON p.id=wr.product_id LEFT JOIN product_models pm ON pm.id=wr.model_id ORDER BY wr.enabled DESC,p.name,pm.code,wr.id';
        $rules = Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $title = 'Regole garanzia'; $user = AdminAuth::user(); $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_rules.php';
    }

    public static function ruleForm(): void
    {
        AdminAuth::requireLogin();
        $pdo = Database::connection(); $id = Validator::int($_GET['id'] ?? 0);
        $rule = ['id'=>0,'product_id'=>'','model_id'=>'','enabled'=>1,'warranty_years'=>'','extension_formula'=>'','registration_days_limit'=>'','invoice_required'=>1,'fgas_required'=>1,'valid_from'=>'','valid_to'=>'','notes'=>''];
        if ($id) {
            $s=$pdo->prepare('SELECT * FROM warranty_rules WHERE id=?');
            $s->execute([$id]);
            $found=$s->fetch(PDO::FETCH_ASSOC);
            if (!$found) { http_response_code(404); exit('Regola non trovata'); }
            $rule=$found;
        }
        self::renderRuleForm($pdo, $rule, []);
    }

    public static function saveRule(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $pdo=Database::connection(); $errors=[];
        $id=Validator::int($_POST['id'] ?? 0);
        $productId=Validator::int($_POST['product_id'] ?? 0) ?: null;
        $modelId=Validator::int($_POST['model_id'] ?? 0) ?: null;
        $years=Validator::int($_POST['warranty_years'] ?? 0);
        if ($years<1 || $years>30) $errors[]='Durata garanzia non valida.';
        if (!$productId && !$modelId) $errors[]='Seleziona almeno un prodotto o un modello.';

        $formula=trim((string)($_POST['extension_formula'] ?? '')) ?: null;
        if ($formula !== null && mb_strlen($formula) > 60) $errors[]='Formula estensione troppo lunga.';

        $limitRaw=trim((string)($_POST['registration_days_limit'] ?? ''));
        $limit=$limitRaw === '' ? null : Validator::int($limitRaw);
        if ($limit !== null && ($limit < 1 || $limit > 3650)) $errors[]='Limite registrazione non valido.';

        $enabled=isset($_POST['enabled'])?1:0;
        $invoice=isset($_POST['invoice_required'])?1:0;
        $fgas=isset($_POST['fgas_required'])?1:0;
        $from=trim((string)($_POST['valid_from'] ?? '')) ?: null;
        $to=trim((string)($_POST['valid_to'] ?? '')) ?: null;
        $notes=trim((string)($_POST['notes'] ?? '')) ?: null;

        if ($from !== null && !WarrantyService::isIsoDate($from)) $errors[]='Data iniziale non valida.';
        if ($to !== null && !WarrantyService::isIsoDate($to)) $errors[]='Data finale non valida.';
        if ($from !== null && $to !== null && $from > $to) $errors[]='La data finale deve essere successiva o uguale alla data iniziale.';

        $modelProductId = null;
        if ($modelId !== null) {
            $s=$pdo->prepare('SELECT product_id FROM product_models WHERE id=? LIMIT 1');
            $s->execute([$modelId]);
            $value=$s->fetchColumn();
            if ($value === false) $errors[]='Modello non valido.';
            else $modelProductId=(int)$value;
        }
        if ($productId !== null) {
            $s=$pdo->prepare('SELECT 1 FROM products WHERE id=? LIMIT 1');
            $s->execute([$productId]);
            if (!$s->fetchColumn()) $errors[]='Prodotto non valido.';
        }
        if ($productId !== null && $modelProductId !== null && $productId !== $modelProductId) {
            $errors[]='Il modello selezionato non appartiene al prodotto indicato.';
        }

        if ($id) {
            $s=$pdo->prepare('SELECT 1 FROM warranty_rules WHERE id=? LIMIT 1');
            $s->execute([$id]);
            if (!$s->fetchColumn()) { http_response_code(404); exit('Regola non trovata'); }
        }

        $rule=[
            'id'=>$id,'product_id'=>$productId ?? '','model_id'=>$modelId ?? '',
            'enabled'=>$enabled,'warranty_years'=>$years,'extension_formula'=>$formula ?? '',
            'registration_days_limit'=>$limit ?? '','invoice_required'=>$invoice,'fgas_required'=>$fgas,
            'valid_from'=>$from ?? '','valid_to'=>$to ?? '','notes'=>$notes ?? '',
        ];
        if ($errors) { self::renderRuleForm($pdo, $rule, array_values(array_unique($errors))); return; }

        if ($id) {
            $s=$pdo->prepare('UPDATE warranty_rules SET product_id=?,model_id=?,enabled=?,warranty_years=?,extension_formula=?,registration_days_limit=?,invoice_required=?,fgas_required=?,valid_from=?,valid_to=?,notes=? WHERE id=?');
            $s->execute([$productId,$modelId,$enabled,$years,$formula,$limit,$invoice,$fgas,$from,$to,$notes,$id]);
            $entityId=$id; $action='warranty_rule.update';
        } else {
            $s=$pdo->prepare('INSERT INTO warranty_rules(product_id,model_id,enabled,warranty_years,extension_formula,registration_days_limit,invoice_required,fgas_required,valid_from,valid_to,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
            $s->execute([$productId,$modelId,$enabled,$years,$formula,$limit,$invoice,$fgas,$from,$to,$notes]);
            $entityId=(int)$pdo->lastInsertId(); $action='warranty_rule.create';
        }
        Audit::log($action,'warranty_rule',$entityId,['years'=>$years,'product_id'=>$productId,'model_id'=>$modelId]);
        header('Location: /admin/warranties/rules'); exit;
    }

    public static function privateFile(string $kind, string $id): void
    {
        AdminAuth::requireLogin();
        $registrationId=(int)$id;
        if (!in_array($kind,['invoice','fgas'],true)) { http_response_code(404); exit; }
        $column=$kind==='invoice'?'invoice_file':'fgas_file';
        $s=Database::connection()->prepare("SELECT {$column} FROM warranty_registrations WHERE id=?"); $s->execute([$registrationId]); $rel=(string)($s->fetchColumn() ?: '');
        if ($rel==='' || str_contains($rel,'..')) { http_response_code(404); exit; }
        $root=realpath(dirname(__DIR__,3).'/storage/private'); $file=realpath(dirname(__DIR__,3).'/storage/private/'.$rel);
        if (!$root || !$file || !str_starts_with($file,$root.DIRECTORY_SEPARATOR) || !is_file($file)) { http_response_code(404); exit; }
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file) ?: 'application/octet-stream';
        $downloadName = $kind === 'invoice' ? 'fattura-' . $registrationId : 'fgas-' . $registrationId;
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if ($extension !== '') $downloadName .= '.' . preg_replace('/[^a-z0-9]+/', '', $extension);
        header('Content-Type: '.$mime);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Content-Disposition: inline; filename="'.$downloadName.'"');
        header('Content-Length: '.filesize($file)); readfile($file); exit;
    }


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
        $number = $existingCertificate ? (string)$existingCertificate['certificate_number'] : self::newCertificateNumber($pdo);
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
        $body = "Gentile {$customer},\r\n\r\nin allegato trova il certificato di estensione della garanzia IDEMA relativo alla registrazione verificata dal nostro reparto assistenza.\r\n\r\nCordiali saluti\r\nIDEMA Clima S.r.l.";
        $safeName = preg_replace('/[^A-Za-z0-9._-]/','_', (string)$row['file_name']) ?: 'Certificato-Garanzia-IDEMA.pdf';
        $attachment = chunk_split(base64_encode((string)file_get_contents($file)));
        $headers = [
            'From: IDEMA Clima <no-reply@rappresentanzeguanzirolisas.it>',
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

    private static function ensureDetailsTable(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE IF NOT EXISTS warranty_registration_details (
            registration_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
            product_type VARCHAR(20) NOT NULL,
            outer_unit VARCHAR(80) NULL,
            combination VARCHAR(255) NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
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
            if (preg_match('/^(.+?)-R32$/i', $model, $match)) {
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
    private static function renderRuleForm(PDO $pdo, array $rule, array $errors): void
    {
        $products = $pdo->query('SELECT id,name FROM products WHERE published=1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $models = $pdo->query('SELECT pm.id,pm.code,p.name product_name FROM product_models pm JOIN products p ON p.id=pm.product_id WHERE pm.published=1 ORDER BY p.name,pm.code')->fetchAll(PDO::FETCH_ASSOC);
        $title='Regola garanzia'; $user=AdminAuth::user(); $csrf=Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_rule_form.php';
    }
}
