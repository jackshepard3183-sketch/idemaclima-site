<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
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
        if (!in_array($status, ['pending','approved','rejected','issued'], true)) $status = 'pending';
        $notes = trim((string)($_POST['admin_notes'] ?? ''));
        $stmt = Database::connection()->prepare('UPDATE warranty_registrations SET status=?,admin_notes=? WHERE id=?');
        $stmt->execute([$status, $notes ?: null, $id]);
        Audit::log('warranty.status', 'warranty_registration', $id, ['status'=>$status]);
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
        if ($id) { $s=$pdo->prepare('SELECT * FROM warranty_rules WHERE id=?'); $s->execute([$id]); $rule=$s->fetch(PDO::FETCH_ASSOC) ?: $rule; }
        $products = $pdo->query('SELECT id,name FROM products WHERE published=1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $models = $pdo->query('SELECT pm.id,pm.code,p.name product_name FROM product_models pm JOIN products p ON p.id=pm.product_id WHERE pm.published=1 ORDER BY p.name,pm.code')->fetchAll(PDO::FETCH_ASSOC);
        $errors=[]; $title='Regola garanzia'; $user=AdminAuth::user(); $csrf=Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_rule_form.php';
    }

    public static function saveRule(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $pdo=Database::connection(); $errors=[];
        $id=Validator::int($_POST['id'] ?? 0); $productId=Validator::int($_POST['product_id'] ?? 0) ?: null; $modelId=Validator::int($_POST['model_id'] ?? 0) ?: null;
        $years=Validator::int($_POST['warranty_years'] ?? 0); if ($years<1 || $years>30) $errors[]='Durata garanzia non valida.';
        if (!$productId && !$modelId) $errors[]='Seleziona almeno un prodotto o un modello.';
        $formula=trim((string)($_POST['extension_formula'] ?? '')) ?: null; $limit=Validator::int($_POST['registration_days_limit'] ?? 0) ?: null;
        $enabled=isset($_POST['enabled'])?1:0; $invoice=isset($_POST['invoice_required'])?1:0; $fgas=isset($_POST['fgas_required'])?1:0;
        $from=trim((string)($_POST['valid_from'] ?? '')) ?: null; $to=trim((string)($_POST['valid_to'] ?? '')) ?: null; $notes=trim((string)($_POST['notes'] ?? '')) ?: null;
        if ($errors) { $_GET['id']=$id; self::ruleForm(); return; }
        if ($id) { $s=$pdo->prepare('UPDATE warranty_rules SET product_id=?,model_id=?,enabled=?,warranty_years=?,extension_formula=?,registration_days_limit=?,invoice_required=?,fgas_required=?,valid_from=?,valid_to=?,notes=? WHERE id=?'); $s->execute([$productId,$modelId,$enabled,$years,$formula,$limit,$invoice,$fgas,$from,$to,$notes,$id]); $entityId=$id; $action='warranty_rule.update'; }
        else { $s=$pdo->prepare('INSERT INTO warranty_rules(product_id,model_id,enabled,warranty_years,extension_formula,registration_days_limit,invoice_required,fgas_required,valid_from,valid_to,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?)'); $s->execute([$productId,$modelId,$enabled,$years,$formula,$limit,$invoice,$fgas,$from,$to,$notes]); $entityId=(int)$pdo->lastInsertId(); $action='warranty_rule.create'; }
        Audit::log($action,'warranty_rule',$entityId,['years'=>$years]);
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
        header('Content-Type: '.$mime); header('Content-Disposition: inline; filename="'.basename($file).'"'); header('Content-Length: '.filesize($file)); readfile($file); exit;
    }
}
