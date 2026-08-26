<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\DataIntegrity;
use App\Core\Database;
use App\Core\Security;
use App\Core\Upload;
use App\Core\Validator;
use PDO;

final class ProductsController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $sql = 'SELECT p.id,p.name,p.slug,p.product_role,p.refrigerant,p.status,p.published,p.sort_order,c.name category_name,COUNT(DISTINCT m.id) model_count,COUNT(DISTINCT pcl.category_id) secondary_category_count FROM products p JOIN product_categories c ON c.id=p.category_id LEFT JOIN product_models m ON m.product_id=p.id LEFT JOIN product_category_links pcl ON pcl.product_id=p.id GROUP BY p.id,p.name,p.slug,p.product_role,p.refrigerant,p.status,p.published,p.sort_order,c.name ORDER BY c.sort_order,c.name,p.sort_order,p.name';
        $products = Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/products.php';
    }

    public static function form(): void
    {
        AdminAuth::requireLogin();
        $pdo = Database::connection();
        $id = Validator::int($_GET['id'] ?? 0);
        $product = ['id'=>0,'category_id'=>'','name'=>'','slug'=>'','description'=>'','product_role'=>'complete_system','refrigerant'=>'','status'=>'active','image_path'=>'','sort_order'=>0,'published'=>1];
        if ($id) {
            $s = $pdo->prepare('SELECT * FROM products WHERE id=?');
            $s->execute([$id]);
            $product = $s->fetch(PDO::FETCH_ASSOC) ?: $product;
        }
        $categories = $pdo->query('SELECT id,name FROM product_categories ORDER BY sort_order,name')->fetchAll(PDO::FETCH_ASSOC);
        $secondaryCategoryIds = [];
        $models = [];
        if ($id) {
            $s = $pdo->prepare('SELECT category_id FROM product_category_links WHERE product_id=? ORDER BY category_id');
            $s->execute([$id]);
            $secondaryCategoryIds = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));

            $s = $pdo->prepare('SELECT * FROM product_models WHERE product_id=? ORDER BY sort_order,code');
            $s->execute([$id]);
            $models = $s->fetchAll(PDO::FETCH_ASSOC);
        }
        $errors = [];
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/product_form.php';
    }

    public static function save(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Sessione non valida');
        }

        $errors = [];
        $id = Validator::int($_POST['id'] ?? 0);
        $categoryId = Validator::int($_POST['category_id'] ?? 0);
        $pdo = Database::connection();
        if ($categoryId < 1 || !DataIntegrity::categoryExists($pdo, $categoryId)) $errors[] = 'Categoria principale non valida.';

        $secondaryCategoryIds = [];
        $postedSecondary = $_POST['secondary_category_ids'] ?? [];
        if (!is_array($postedSecondary)) $postedSecondary = [];
        foreach ($postedSecondary as $rawId) {
            $secondaryId = Validator::int($rawId);
            if ($secondaryId < 1 || $secondaryId === $categoryId) continue;
            if (!DataIntegrity::categoryExists($pdo, $secondaryId)) {
                $errors[] = 'Una delle categorie aggiuntive non è valida.';
                continue;
            }
            $secondaryCategoryIds[$secondaryId] = $secondaryId;
        }
        $secondaryCategoryIds = array_values($secondaryCategoryIds);

        $name = Validator::requiredString($_POST['name'] ?? '', 'Nome', 180, $errors);
        $slug = Validator::slug((string)($_POST['slug'] ?? ''));
        if ($slug === '') $slug = Validator::slug($name);
        $description = trim((string)($_POST['description'] ?? ''));
        $roles = ['complete_system','outdoor_unit','indoor_unit','accessory','tank','controller','other'];
        $role = (string)($_POST['product_role'] ?? 'complete_system');
        if (!in_array($role, $roles, true)) $errors[] = 'Ruolo non valido.';
        $refrigerant = Validator::optionalString($_POST['refrigerant'] ?? '', 32, 'Refrigerante', $errors);
        $statuses = ['active','discontinued','unavailable'];
        $status = (string)($_POST['status'] ?? 'active');
        if (!in_array($status, $statuses, true)) $errors[] = 'Stato non valido.';
        $sort = Validator::int($_POST['sort_order'] ?? 0);
        $published = Validator::bool($_POST['published'] ?? 0);

        $q = $pdo->prepare('SELECT id FROM products WHERE slug=? AND id<>?');
        $q->execute([$slug, $id]);
        if ($q->fetch()) $errors[] = 'Slug già utilizzato.';

        $existingImage = '';
        if ($id) {
            $q = $pdo->prepare('SELECT image_path FROM products WHERE id=?');
            $q->execute([$id]);
            $existingImage = (string)($q->fetchColumn() ?: '');
        }

        $uploaded = Upload::image('image_file', $errors);
        $image = $uploaded['path'] ?? $existingImage;

        if ($errors) {
            if ($uploaded) Upload::removeManaged($uploaded['path']);
            $product = ['id'=>$id,'category_id'=>$categoryId,'name'=>$name,'slug'=>$slug,'description'=>$description,'product_role'=>$role,'refrigerant'=>$refrigerant,'status'=>$status,'image_path'=>$image,'sort_order'=>$sort,'published'=>$published];
            $categories = $pdo->query('SELECT id,name FROM product_categories ORDER BY sort_order,name')->fetchAll(PDO::FETCH_ASSOC);
            $models = [];
            if ($id) {
                $m = $pdo->prepare('SELECT * FROM product_models WHERE product_id=? ORDER BY sort_order,code');
                $m->execute([$id]);
                $models = $m->fetchAll(PDO::FETCH_ASSOC);
            }
            $user = AdminAuth::user();
            $csrf = Security::csrfToken();
            require dirname(__DIR__, 2) . '/Views/admin/product_form.php';
            return;
        }

        try {
            $pdo->beginTransaction();
            if ($id) {
                $s = $pdo->prepare('UPDATE products SET category_id=?,name=?,slug=?,description=?,product_role=?,refrigerant=?,status=?,image_path=?,sort_order=?,published=? WHERE id=?');
                $s->execute([$categoryId,$name,$slug,$description,$role,$refrigerant,$status,$image,$sort,$published,$id]);
                $entityId = $id;
                $action = 'product.update';
            } else {
                $s = $pdo->prepare('INSERT INTO products(category_id,name,slug,description,product_role,refrigerant,status,image_path,sort_order,published) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $s->execute([$categoryId,$name,$slug,$description,$role,$refrigerant,$status,$image,$sort,$published]);
                $entityId = (int)$pdo->lastInsertId();
                $action = 'product.create';
            }

            $pdo->prepare('DELETE FROM product_category_links WHERE product_id=?')->execute([$entityId]);
            if ($secondaryCategoryIds !== []) {
                $link = $pdo->prepare('INSERT INTO product_category_links(product_id,category_id) VALUES(?,?)');
                foreach ($secondaryCategoryIds as $secondaryId) $link->execute([$entityId,$secondaryId]);
            }

            Audit::log($action, 'product', $entityId, [
                'name'=>$name,
                'image_path'=>$image,
                'category_id'=>$categoryId,
                'secondary_category_ids'=>$secondaryCategoryIds,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($uploaded) Upload::removeManaged($uploaded['path']);
            throw $e;
        }

        if ($uploaded && $existingImage && $existingImage !== $image) Upload::removeManaged($existingImage);
        header('Location: /admin/products/form?id=' . $entityId);
        exit;
    }

    public static function saveModel(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            exit('Sessione non valida');
        }
        $productId = Validator::int($_POST['product_id'] ?? 0);
        $id = Validator::int($_POST['id'] ?? 0);
        $code = trim((string)($_POST['code'] ?? ''));
        $pdo = Database::connection();
        $p = $pdo->prepare('SELECT 1 FROM products WHERE id=?');
        $p->execute([$productId]);
        if ($productId < 1 || !$p->fetchColumn() || $code === '') {
            http_response_code(422);
            exit('Prodotto e codice modello validi sono obbligatori');
        }
        $name = trim((string)($_POST['name'] ?? ''));
        $sort = Validator::int($_POST['sort_order'] ?? 0);
        $published = Validator::bool($_POST['published'] ?? 0);
        try {
            if ($id) {
                $s = $pdo->prepare('UPDATE product_models SET code=?,name=?,sort_order=?,published=? WHERE id=? AND product_id=?');
                $s->execute([$code,$name?:null,$sort,$published,$id,$productId]);
                if ($s->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM product_models WHERE id=? AND product_id=?');
                    $exists->execute([$id,$productId]);
                    if (!$exists->fetchColumn()) { http_response_code(404); exit('Modello non trovato per il prodotto selezionato.'); }
                }
                $entityId = $id;
                $action = 'model.update';
            } else {
                $s = $pdo->prepare('INSERT INTO product_models(product_id,code,name,sort_order,published) VALUES(?,?,?,?,?)');
                $s->execute([$productId,$code,$name?:null,$sort,$published]);
                $entityId = (int)$pdo->lastInsertId();
                $action = 'model.create';
            }
            Audit::log($action, 'product_model', $entityId, ['code'=>$code,'product_id'=>$productId]);
        } catch (\PDOException) {
            http_response_code(422);
            exit('Codice modello già presente per questo prodotto.');
        }
        header('Location: /admin/products/form?id=' . $productId);
        exit;
    }
}
