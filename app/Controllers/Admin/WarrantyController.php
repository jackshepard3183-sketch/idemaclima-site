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
        $stmt = $pdo->prepare('SELECT wr.*,pm.code,p.name product_name FROM warranty_registrations wr JOIN product_models pm ON pm.id=wr.model_id JOIN products p ON p.id=pm.product_id WHERE wr.id=?');
        $stmt->execute([$id]);
        $registration = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$registration) { http_response_code(404); exit('Registrazione non trovata'); }
        $stmt = $pdo->prepare('SELECT * FROM warranty_units WHERE registration_id=? ORDER BY id');
        $stmt->execute([$id]); $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            $certificate = $pdo->prepare('SELECT 1 FROM warranty_certificates WHERE registration_id=? LIMIT 1');
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

    private static function renderRuleForm(PDO $pdo, array $rule, array $errors): void
    {
        $products = $pdo->query('SELECT id,name FROM products WHERE published=1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $models = $pdo->query('SELECT pm.id,pm.code,p.name product_name FROM product_models pm JOIN products p ON p.id=pm.product_id WHERE pm.published=1 ORDER BY p.name,pm.code')->fetchAll(PDO::FETCH_ASSOC);
        $title='Regola garanzia'; $user=AdminAuth::user(); $csrf=Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_rule_form.php';
    }
}
