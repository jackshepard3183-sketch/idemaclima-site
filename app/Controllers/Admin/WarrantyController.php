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
    use WarrantyRulesTrait;
    use WarrantyCertificateActionsTrait;
    use WarrantyCertificateLayoutTrait;
    public static function registrations(): void
    {
        AdminAuth::requireLogin();
                if (isset($_GET['import'])) { WarrantyImportController::index(); return; }
                $sql = 'SELECT wr.id,wr.certificate_number,wr.import_review_warning,wr.customer_first_name,wr.customer_last_name,wr.email,wr.invoice_date,wr.status,wr.warranty_years,wr.created_at,
                       CASE WHEN LOWER(d.product_type) IN (\'multi\',\'multi split\') AND d.outer_unit <> pm.code THEN d.outer_unit ELSE pm.code END code,
                       CASE WHEN LOWER(d.product_type) IN (\'multi\',\'multi split\') AND d.outer_unit <> pm.code
                           AND NOT ((d.outer_unit=\'2MW-50-R32\' AND pm.code=\'2MWTZ-50-R32\')
                             OR (d.outer_unit=\'3MW-70-R32\' AND pm.code=\'3MWTZ-70-R32\'))
                      THEN \'Multi Split da verificare\'
                      WHEN d.outer_unit <> pm.code THEN CONCAT(\'Multi Split (equiv. \',pm.code,\')\')
                      ELSE p.name END product_name
                FROM warranty_registrations wr JOIN product_models pm ON pm.id=wr.model_id JOIN products p ON p.id=pm.product_id
                LEFT JOIN warranty_registration_details d ON d.registration_id=wr.id
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
        $stmt = $pdo->prepare('SELECT wr.*,CASE WHEN LOWER(d.product_type) IN (\'multi\',\'multi split\') AND d.outer_unit <> pm.code THEN d.outer_unit ELSE pm.code END code,
            CASE WHEN LOWER(d.product_type) IN (\'multi\',\'multi split\') AND d.outer_unit <> pm.code
                           AND NOT ((d.outer_unit=\'2MW-50-R32\' AND pm.code=\'2MWTZ-50-R32\')
                             OR (d.outer_unit=\'3MW-70-R32\' AND pm.code=\'3MWTZ-70-R32\'))
                      THEN \'Multi Split da verificare\'
                      WHEN d.outer_unit <> pm.code THEN CONCAT(\'Multi Split (equiv. \',pm.code,\')\')
                      ELSE p.name END product_name
            FROM warranty_registrations wr JOIN product_models pm ON pm.id=wr.model_id JOIN products p ON p.id=pm.product_id
            LEFT JOIN warranty_registration_details d ON d.registration_id=wr.id WHERE wr.id=?');
        $stmt->execute([$id]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registration) { http_response_code(404); exit('Registrazione non trovata'); }
        $stmt = $pdo->prepare('SELECT * FROM warranty_units WHERE registration_id=? ORDER BY id');
        $stmt->execute([$id]); $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT * FROM warranty_registration_details WHERE registration_id=?');
        $stmt->execute([$id]); $details = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $stmt = $pdo->prepare('SELECT * FROM warranty_generated_certificates WHERE registration_id=?');
        $stmt->execute([$id]); $certificate = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $stmt = $pdo->prepare('SELECT action,metadata,created_at FROM audit_log WHERE entity_type="warranty_registration" AND entity_id=? ORDER BY created_at DESC,id DESC LIMIT 100');
        $stmt->execute([$id]); $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $modelRows = $pdo->query('SELECT pm.id,pm.code,p.name product_name FROM product_models pm JOIN products p ON p.id=pm.product_id JOIN product_categories c ON c.id=p.category_id WHERE pm.published=1 OR (pm.published=0 AND pm.code=p.name AND c.name=\'Multi Split\' AND EXISTS (SELECT 1 FROM warranty_rules r WHERE r.product_id=p.id AND r.enabled=1)) ORDER BY p.name,pm.code')->fetchAll(PDO::FETCH_ASSOC);
        $modelsByKey = [];
        foreach ($modelRows as $model) {
            $key = strtoupper(trim((string)$model['product_name'])) . "\0" . strtoupper(trim((string)$model['code']));
            if (!isset($modelsByKey[$key]) || (int)$model['id'] === (int)$registration['model_id']) {
                $modelsByKey[$key] = $model;
            }
        }
        $models = array_values($modelsByKey);
        $title = 'Garanzia ' . ((string)($registration['certificate_number'] ?? '') !== '' ? (string)$registration['certificate_number'] : 'in verifica'); $user = AdminAuth::user(); $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_registration.php';
    }

    public static function updateStatus(): void
    {
        AdminAuth::requireLogin();
                if (isset($_GET['import'])) { WarrantyImportController::run(); return; }
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $id = Validator::int($_POST['id'] ?? 0);
        $status = (string)($_POST['status'] ?? 'pending');
        if (!in_array($status, ['pending','approved','rejected','issued'], true)) { http_response_code(422); exit('Stato non valido'); }
        $notes = trim((string)($_POST['admin_notes'] ?? ''));
        if (mb_strlen($notes) > 10000) { http_response_code(422); exit('Note troppo lunghe'); }

        $pdo = Database::connection();
        $exists = $pdo->prepare('SELECT status,warranty_rule_id,warranty_years FROM warranty_registrations WHERE id=? LIMIT 1');
        $exists->execute([$id]);
        $current = $exists->fetch(PDO::FETCH_ASSOC);
        if (!$current) { http_response_code(404); exit('Registrazione non trovata'); }
        $previous = (string)$current['status'];
        if (in_array($status, ['approved','issued'], true)
            && ((int)$current['warranty_rule_id'] < 1 || (int)$current['warranty_years'] < 1)) {
            http_response_code(422); exit('Verifica il modello principale e la regola garanzia prima di approvare la pratica.');
        }

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
        header('Location: /idemaclima/admin/warranties/registration?id=' . $id); exit;
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
            if ($field === 'combination') $data[$field] = strtoupper($data[$field]);
            if ($data[$field] === '') { http_response_code(422); exit('Compila tutti i campi obbligatori.'); }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) { http_response_code(422); exit('Email non valida.'); }
        if (!WarrantyService::isIsoDate($data['invoice_date'])) { http_response_code(422); exit('Data fattura non valida.'); }
        $phone = trim((string)($_POST['phone'] ?? ''));
        $productType = (string)($_POST['product_type'] ?? 'mono');
        if (!in_array($productType, ['mono','multi'], true)) $productType = 'mono';
        $outerUnit = strtoupper(trim((string)($_POST['outer_unit'] ?? '')));
        $serials = is_array($_POST['unit_serials'] ?? null) ? $_POST['unit_serials'] : [];

        $pdo = Database::connection();
        self::ensureDetailsTable($pdo); self::ensureCertificateTable($pdo);
        $stmt = $pdo->prepare('SELECT status FROM warranty_registrations WHERE id=?');
        $stmt->execute([$id]); $currentStatus = $stmt->fetchColumn();
        if ($currentStatus === false) { http_response_code(404); exit('Registrazione non trovata'); }
        $stmt = $pdo->prepare('SELECT pm.code FROM product_models pm JOIN products p ON p.id=pm.product_id JOIN product_categories c ON c.id=p.category_id
            WHERE pm.id=? AND (pm.published=1 OR (pm.published=0 AND pm.code=p.name AND c.name=\'Multi Split\'))');
        $stmt->execute([$modelId]);
        $selectedModelCode = $stmt->fetchColumn();
        if ($selectedModelCode === false) { http_response_code(422); exit('Modello non valido.'); }
        if ($productType === 'multi' && WarrantyService::warrantyModelCode($outerUnit) !== (string)$selectedModelCode) {
            http_response_code(422); exit('Seleziona il modello dell’unità esterna indicata nella pratica.');
        }
        $rule = WarrantyService::applicableRule($pdo, $modelId, $data['invoice_date']);
        if (!$rule) { http_response_code(422); exit('Regola garanzia non disponibile per il modello e la data fattura.'); }
        if ($productType === 'mono' && $outerUnit === '') $outerUnit = (string)$selectedModelCode;
        $stmt = $pdo->prepare('SELECT file_path FROM warranty_generated_certificates WHERE registration_id=?');
        $stmt->execute([$id]); $certificatePath = $stmt->fetchColumn() ?: null;

        $unitStmt=$pdo->prepare('SELECT id,unit_type FROM warranty_units WHERE registration_id=? ORDER BY id');
        $unitStmt->execute([$id]); $registeredUnits=$unitStmt->fetchAll(PDO::FETCH_ASSOC);
        $outdoorCount=0;$indoorCount=0;$allowedUnitIds=[];
        foreach($registeredUnits as $unit){$allowedUnitIds[(int)$unit['id']]=true;if($unit['unit_type']==='outdoor')$outdoorCount++;elseif($unit['unit_type']==='indoor')$indoorCount++;}
        $expectedIndoor=1;
        if($productType==='multi'){
            $expectedIndoor=0;
            foreach(preg_split('/\s*\+\s*/',$data['combination'])?:[] as $part){$quantity=1;if(preg_match('/^\s*(\d+)\s*x\b/i',$part,$match))$quantity=max(1,(int)$match[1]);$expectedIndoor+=$quantity;}
            $expectedIndoor=max(1,min(3,$expectedIndoor));
        }
        if($outdoorCount!==1||$indoorCount!==$expectedIndoor){http_response_code(422);exit('La combinazione richiede 1 unità esterna e '.$expectedIndoor.' unità interne. Correggi la combinazione oppure i seriali prima di salvare.');}
        if(count($serials)!==count($registeredUnits)){http_response_code(422);exit('Compila tutti i seriali delle unità registrate.');}
        foreach($serials as $unitId=>$serial){if(!isset($allowedUnitIds[(int)$unitId])){http_response_code(422);exit('Elenco seriali non valido.');}}

        $pdo->beginTransaction();
        try {
            $newStatus = $currentStatus === 'issued' ? 'approved' : $currentStatus;
            $stmt = $pdo->prepare('UPDATE warranty_registrations SET model_id=?,warranty_rule_id=?,warranty_years=?,extension_formula=?,registration_days_limit=?,invoice_required_snapshot=?,fgas_required_snapshot=?,customer_first_name=?,customer_last_name=?,fiscal_code=?,email=?,phone=?,address=?,postal_code=?,city=?,province=?,region=?,invoice_date=?,status=? WHERE id=?');
            $stmt->execute([$modelId,(int)$rule['id'],(int)$rule['warranty_years'],$rule['extension_formula'],$rule['registration_days_limit'],(int)$rule['invoice_required'],(int)$rule['fgas_required'],Validator::naturalText($data['customer_first_name']),Validator::naturalText($data['customer_last_name']),strtoupper($data['fiscal_code']),strtolower($data['email']),$phone ?: null,Validator::naturalText($data['address']),strtoupper($data['postal_code']),Validator::naturalText($data['city']),Validator::provinceCode($data['province']),Validator::naturalText($data['region']),$data['invoice_date'],$newStatus,$id]);
            $pdo->prepare('UPDATE warranty_units SET model_id=? WHERE registration_id=? AND unit_type=\'outdoor\'')->execute([$modelId,$id]);
            $updateSerial = $pdo->prepare('UPDATE warranty_units SET serial_number=? WHERE id=? AND registration_id=?');
            foreach ($serials as $unitId => $serial) {
                $serial = strtoupper(trim((string)$serial));
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

}
