<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\DataIntegrity;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

final class CategoriesController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $categories = Database::connection()->query('SELECT c.*, p.name parent_name FROM product_categories c LEFT JOIN product_categories p ON p.id=c.parent_id ORDER BY c.sort_order,c.name')->fetchAll(PDO::FETCH_ASSOC);
        $user = AdminAuth::user(); $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/categories.php';
    }

    public static function form(): void
    {
        AdminAuth::requireLogin();
        $id = Validator::int($_GET['id'] ?? 0);
        $category = ['id'=>0,'parent_id'=>null,'name'=>'','slug'=>'','sort_order'=>0,'content_status'=>'draft','published'=>0];
        if ($id) {
            $s=Database::connection()->prepare('SELECT * FROM product_categories WHERE id=?'); $s->execute([$id]);
            $category=$s->fetch(PDO::FETCH_ASSOC) ?: $category;
        }
        $categories=Database::connection()->query('SELECT id,name FROM product_categories ORDER BY sort_order,name')->fetchAll(PDO::FETCH_ASSOC);
        $errors=[]; $user=AdminAuth::user(); $csrf=Security::csrfToken();
        require dirname(__DIR__,2).'/Views/admin/category_form.php';
    }

    public static function save(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $errors=[]; $id=Validator::int($_POST['id']??0);
        $name=Validator::requiredString($_POST['name']??'', 'Nome',160,$errors);
        $slug=Validator::slug((string)($_POST['slug']??'')); if($slug==='') $slug=Validator::slug($name);
        $parentId=Validator::int($_POST['parent_id']??0); $parentId=$parentId>0?$parentId:null;
        $sort=Validator::int($_POST['sort_order']??0); $contentStatus=(string)($_POST['content_status']??'draft');
        if(!in_array($contentStatus,['draft','published','hidden'],true))$errors[]='Stato contenuto non valido.';
        $published=$contentStatus==='published'?1:0;
        $pdo=Database::connection();

        $parentError = DataIntegrity::categoryParentError($pdo, $id, $parentId);
        if ($parentError !== null) $errors[] = $parentError;

        $q=$pdo->prepare('SELECT id FROM product_categories WHERE slug=? AND id<>?');
        $q->execute([$slug,$id]);
        if($q->fetch()) $errors[]='Slug già utilizzato.';

        if($errors){ self::renderErrors($id,$parentId,$name,$slug,$sort,$contentStatus,$published,$errors); return; }
        if($id){$s=$pdo->prepare('UPDATE product_categories SET parent_id=?,name=?,slug=?,sort_order=?,content_status=?,published=? WHERE id=?');$s->execute([$parentId,$name,$slug,$sort,$contentStatus,$published,$id]);$entityId=$id;$action='category.update';}
        else{$s=$pdo->prepare('INSERT INTO product_categories(parent_id,name,slug,sort_order,content_status,published) VALUES(?,?,?,?,?,?)');$s->execute([$parentId,$name,$slug,$sort,$contentStatus,$published]);$entityId=(int)$pdo->lastInsertId();$action='category.create';}
        Audit::log($action,'product_category',$entityId,['name'=>$name,'parent_id'=>$parentId]); header('Location: /admin/categories'); exit;
    }

    private static function renderErrors(int $id, ?int $parentId,string $name,string $slug,int $sort,string $contentStatus,int $published,array $errors):void
    {
        $category=['id'=>$id,'parent_id'=>$parentId,'name'=>$name,'slug'=>$slug,'sort_order'=>$sort,'content_status'=>$contentStatus,'published'=>$published];
        $categories=Database::connection()->query('SELECT id,name FROM product_categories ORDER BY sort_order,name')->fetchAll(PDO::FETCH_ASSOC);
        $user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/category_form.php';
    }
}
