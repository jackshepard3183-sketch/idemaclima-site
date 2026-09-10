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
        $sql = 'SELECT p.id,p.name,p.slug,p.product_role,p.refrigerant,p.status,p.content_status,p.published,p.sort_order,c.name category_name,parent.name category_group,COUNT(DISTINCT m.id) model_count,COUNT(DISTINCT pcl.category_id) secondary_category_count FROM products p JOIN product_categories c ON c.id=p.category_id LEFT JOIN product_categories parent ON parent.id=c.parent_id LEFT JOIN product_models m ON m.product_id=p.id LEFT JOIN product_category_links pcl ON pcl.product_id=p.id GROUP BY p.id,p.name,p.slug,p.product_role,p.refrigerant,p.status,p.content_status,p.published,p.sort_order,c.name,parent.name,parent.sort_order ORDER BY COALESCE(parent.sort_order,c.sort_order),COALESCE(parent.name,c.name),c.sort_order,c.name,p.sort_order,p.name';
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
        $product = ['id'=>0,'category_id'=>'','name'=>'','slug'=>'','description'=>'','product_role'=>'complete_system','refrigerant'=>'','status'=>'active','content_status'=>'draft','image_path'=>'','sort_order'=>0,'published'=>0];
        if ($id) {
            $s = $pdo->prepare('SELECT * FROM products WHERE id=?');
            $s->execute([$id]);
            $product = $s->fetch(PDO::FETCH_ASSOC) ?: $product;
        }
        $categories = $pdo->query('SELECT id,name FROM product_categories ORDER BY sort_order,name')->fetchAll(PDO::FETCH_ASSOC);
        $secondaryCategoryIds = [];
        $models = [];
        $features = $specifications = $accessories = [];
        if ($id) {
            $s = $pdo->prepare('SELECT category_id FROM product_category_links WHERE product_id=? ORDER BY category_id');
            $s->execute([$id]);
            $secondaryCategoryIds = array_map('intval', $s->fetchAll(PDO::FETCH_COLUMN));

            $s = $pdo->prepare('SELECT * FROM product_models WHERE product_id=? ORDER BY sort_order,code');
            $s->execute([$id]);
            $models = $s->fetchAll(PDO::FETCH_ASSOC);
            foreach (['features'=>'product_features','specifications'=>'product_specifications','accessories'=>'product_accessories'] as $key=>$table) {
                $s = $pdo->prepare("SELECT * FROM {$table} WHERE product_id=? ORDER BY sort_order,id");
                $s->execute([$id]);
                ${$key} = $s->fetchAll(PDO::FETCH_ASSOC);
            }
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
        $contentStatus = (string)($_POST['content_status'] ?? 'draft');
        if (!in_array($contentStatus, ['draft','published','hidden'], true)) $errors[] = 'Stato contenuto non valido.';
        $published = $contentStatus === 'published' ? 1 : 0;

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
            $product = ['id'=>$id,'category_id'=>$categoryId,'name'=>$name,'slug'=>$slug,'description'=>$description,'product_role'=>$role,'refrigerant'=>$refrigerant,'status'=>$status,'content_status'=>$contentStatus,'image_path'=>$image,'sort_order'=>$sort,'published'=>$published];
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
                $s = $pdo->prepare('UPDATE products SET category_id=?,name=?,slug=?,description=?,product_role=?,refrigerant=?,status=?,content_status=?,image_path=?,sort_order=?,published=? WHERE id=?');
                $s->execute([$categoryId,$name,$slug,$description,$role,$refrigerant,$status,$contentStatus,$image,$sort,$published,$id]);
                $entityId = $id;
                $action = 'product.update';
            } else {
                $s = $pdo->prepare('INSERT INTO products(category_id,name,slug,description,product_role,refrigerant,status,content_status,image_path,sort_order,published) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $s->execute([$categoryId,$name,$slug,$description,$role,$refrigerant,$status,$contentStatus,$image,$sort,$published]);
                $entityId = (int)$pdo->lastInsertId();
                $action = 'product.create';
            }

            $pdo->prepare('DELETE FROM product_category_links WHERE product_id=?')->execute([$entityId]);
            if ($secondaryCategoryIds !== []) {
                $link = $pdo->prepare('INSERT INTO product_category_links(product_id,category_id) VALUES(?,?)');
                foreach ($secondaryCategoryIds as $secondaryId) $link->execute([$entityId,$secondaryId]);
            }

            self::replaceDetails($pdo, $entityId, $_POST);

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
        $contentStatus = (string)($_POST['content_status'] ?? 'published');
        if (!in_array($contentStatus, ['draft','published','hidden'], true)) $contentStatus = 'draft';
        $published = $contentStatus === 'published' ? 1 : 0;
        try {
            if ($id) {
                $s = $pdo->prepare('UPDATE product_models SET code=?,name=?,content_status=?,sort_order=?,published=? WHERE id=? AND product_id=?');
                $s->execute([$code,$name?:null,$contentStatus,$sort,$published,$id,$productId]);
                if ($s->rowCount() === 0) {
                    $exists = $pdo->prepare('SELECT 1 FROM product_models WHERE id=? AND product_id=?');
                    $exists->execute([$id,$productId]);
                    if (!$exists->fetchColumn()) { http_response_code(404); exit('Modello non trovato per il prodotto selezionato.'); }
                }
                $entityId = $id;
                $action = 'model.update';
            } else {
                $s = $pdo->prepare('INSERT INTO product_models(product_id,code,name,content_status,sort_order,published) VALUES(?,?,?,?,?,?)');
                $s->execute([$productId,$code,$name?:null,$contentStatus,$sort,$published]);
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

    public static function duplicate(): void
    {
        AdminAuth::requireLogin(); self::csrf();
        $id = Validator::int($_POST['id'] ?? 0); $pdo = Database::connection();
        $s=$pdo->prepare('SELECT * FROM products WHERE id=?');$s->execute([$id]);$source=$s->fetch(PDO::FETCH_ASSOC);
        if (!$source) { http_response_code(404); exit('Prodotto non trovato'); }
        $pdo->beginTransaction();
        try {
            $slug = self::uniqueSlug($pdo, (string)$source['slug'].'-copia');
            $q=$pdo->prepare("INSERT INTO products(category_id,name,slug,description,product_role,refrigerant,status,content_status,image_path,sort_order,published) VALUES(?,?,?,?,?,?,?,?,?,?,0)");
            $q->execute([$source['category_id'],$source['name'].' (copia)',$slug,$source['description'],$source['product_role'],$source['refrigerant'],$source['status'],'draft',$source['image_path'],$source['sort_order']]);
            $newId=(int)$pdo->lastInsertId();
            foreach (['product_category_links'=>'category_id','product_models'=>'code,name,content_status,sort_order,published','product_features'=>'label,sort_order','product_specifications'=>'specification_key,specification_value,sort_order','product_accessories'=>'code,name,description,sort_order,published'] as $table=>$columns) {
                $columnList=explode(',',$columns);$select=implode(',',array_map('trim',$columnList));
                $pdo->prepare("INSERT INTO {$table}(product_id,{$select}) SELECT ?,{$select} FROM {$table} WHERE product_id=?")->execute([$newId,$id]);
            }
            Audit::log('product.duplicate','product',$newId,['source_id'=>$id]);$pdo->commit();
        } catch (\Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
        header('Location: /admin/products/form?id='.$newId); exit;
    }

    public static function delete(): void
    {
        AdminAuth::requireLogin(); self::csrf();
        $id=Validator::int($_POST['id']??0);$pdo=Database::connection();
        $s=$pdo->prepare('SELECT image_path FROM products WHERE id=?');$s->execute([$id]);$image=$s->fetchColumn();
        if ($image===false) { http_response_code(404); exit('Prodotto non trovato'); }
        try {$pdo->prepare('DELETE FROM products WHERE id=?')->execute([$id]);Audit::log('product.delete','product',$id);}
        catch (\PDOException) { http_response_code(409); exit('Il prodotto ha collegamenti che ne impediscono la cancellazione. Impostalo come Nascosto.'); }
        if(is_string($image)&&$image!=='')Upload::removeManaged($image);header('Location: /admin/products');exit;
    }

    private static function replaceDetails(PDO $pdo,int $productId,array $input):void
    {
        foreach(['product_features','product_specifications','product_accessories'] as $table)$pdo->prepare("DELETE FROM {$table} WHERE product_id=?")->execute([$productId]);
        $features=preg_split('/\R/u',(string)($input['features_text']??''))?:[];$q=$pdo->prepare('INSERT INTO product_features(product_id,label,sort_order) VALUES(?,?,?)');$order=0;
        foreach($features as $label){$label=trim($label);if($label!=='')$q->execute([$productId,mb_substr($label,0,255),$order++]);}
        $specs=preg_split('/\R/u',(string)($input['specifications_text']??''))?:[];$q=$pdo->prepare('INSERT INTO product_specifications(product_id,specification_key,specification_value,sort_order) VALUES(?,?,?,?)');$order=0;
        foreach($specs as $line){[$key,$value]=array_pad(explode('|',$line,2),2,'');$key=trim($key);$value=trim($value);if($key!==''&&$value!=='')$q->execute([$productId,mb_substr($key,0,160),mb_substr($value,0,255),$order++]);}
        $items=preg_split('/\R/u',(string)($input['accessories_text']??''))?:[];$q=$pdo->prepare('INSERT INTO product_accessories(product_id,code,name,description,sort_order,published) VALUES(?,?,?,?,?,1)');$order=0;
        foreach($items as $line){[$code,$name,$description]=array_pad(explode('|',$line,3),3,'');$name=trim($name);if($name!=='')$q->execute([$productId,trim($code)?:null,mb_substr($name,0,200),trim($description)?:null,$order++]);}
    }

    private static function uniqueSlug(PDO $pdo,string $base):string
    {
        $slug=Validator::slug($base);$candidate=$slug;$i=2;$q=$pdo->prepare('SELECT 1 FROM products WHERE slug=?');
        while(true){$q->execute([$candidate]);if(!$q->fetchColumn())return $candidate;$candidate=$slug.'-'.$i++;}
    }

    private static function csrf():void
    {
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
    }
}
