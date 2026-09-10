<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

trait WarrantyRulesTrait
{
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


    private static function renderRuleForm(PDO $pdo, array $rule, array $errors): void
    {
        $products = $pdo->query('SELECT id,name FROM products WHERE published=1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $models = $pdo->query('SELECT pm.id,pm.code,p.name product_name FROM product_models pm JOIN products p ON p.id=pm.product_id WHERE pm.published=1 ORDER BY p.name,pm.code')->fetchAll(PDO::FETCH_ASSOC);
        $title='Regola garanzia'; $user=AdminAuth::user(); $csrf=Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_rule_form.php';
    }
}

