<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Upload;
use App\Core\Validator;
use PDO;

final class DocumentsController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $sql = 'SELECT d.id,d.title,d.filename,d.file_path,d.document_year,d.revision,d.published,d.sort_order,t.name type_name FROM documents d JOIN document_types t ON t.id=d.document_type_id ORDER BY d.sort_order,d.created_at DESC,d.id DESC';
        $documents = Database::connection()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/documents.php';
    }

    public static function form(): void
    {
        AdminAuth::requireLogin();
        $id = Validator::int($_GET['id'] ?? 0);
        $document = ['id'=>0,'document_type_id'=>'','title'=>'','filename'=>'','file_path'=>'','revision'=>'','document_year'=>'','published'=>1,'sort_order'=>0,'category_id'=>'','product_id'=>'','model_id'=>''];
        $pdo = Database::connection();
        if ($id) {
            $s = $pdo->prepare('SELECT d.*,dl.category_id,dl.product_id,dl.model_id FROM documents d LEFT JOIN document_links dl ON dl.document_id=d.id WHERE d.id=? ORDER BY dl.id LIMIT 1');
            $s->execute([$id]);
            $document = $s->fetch(PDO::FETCH_ASSOC) ?: $document;
        }
        [$types,$categories,$products,$models] = self::formOptions($pdo);
        $errors = [];
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/document_form.php';
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
        $typeId = Validator::int($_POST['document_type_id'] ?? 0);
        if ($typeId < 1) $errors[] = 'Tipo documento obbligatorio.';
        $title = Validator::requiredString($_POST['title'] ?? '', 'Titolo', 255, $errors);
        $existingFilename = Validator::optionalString($_POST['existing_filename'] ?? '', 255, 'Nome file', $errors);
        $existingPath = Validator::optionalString($_POST['existing_file_path'] ?? '', 500, 'Percorso file', $errors);
        $revision = Validator::optionalString($_POST['revision'] ?? '', 80, 'Revisione', $errors);
        $year = Validator::int($_POST['document_year'] ?? 0);
        $year = $year > 0 ? $year : null;
        if ($year !== null && ($year < 1990 || $year > 2200)) $errors[] = 'Anno non valido.';
        $published = Validator::bool($_POST['published'] ?? 0);
        $sort = Validator::int($_POST['sort_order'] ?? 0);
        $categoryId = Validator::int($_POST['category_id'] ?? 0) ?: null;
        $productId = Validator::int($_POST['product_id'] ?? 0) ?: null;
        $modelId = Validator::int($_POST['model_id'] ?? 0) ?: null;
        if (!$categoryId && !$productId && !$modelId) $errors[] = 'Collega il documento almeno a categoria, prodotto o modello.';

        $uploaded = Upload::pdf('document_file', $errors);
        $filename = $uploaded['filename'] ?? $existingFilename;
        $path = $uploaded['path'] ?? $existingPath;
        if ($filename === '' || $path === '') {
            $errors[] = 'Carica un file PDF.';
        }

        $pdo = Database::connection();
        if ($errors) {
            if ($uploaded) Upload::removeManaged($uploaded['path']);
            $document = ['id'=>$id,'document_type_id'=>$typeId,'title'=>$title,'filename'=>$filename,'file_path'=>$path,'revision'=>$revision,'document_year'=>$year,'published'=>$published,'sort_order'=>$sort,'category_id'=>$categoryId,'product_id'=>$productId,'model_id'=>$modelId];
            [$types,$categories,$products,$models] = self::formOptions($pdo);
            $user = AdminAuth::user();
            $csrf = Security::csrfToken();
            require dirname(__DIR__, 2) . '/Views/admin/document_form.php';
            return;
        }

        $oldPath = '';
        if ($id) {
            $s = $pdo->prepare('SELECT file_path FROM documents WHERE id=?');
            $s->execute([$id]);
            $oldPath = (string)($s->fetchColumn() ?: '');
        }

        $pdo->beginTransaction();
        try {
            if ($id) {
                $s = $pdo->prepare('UPDATE documents SET document_type_id=?,title=?,filename=?,file_path=?,revision=?,document_year=?,published=?,sort_order=? WHERE id=?');
                $s->execute([$typeId,$title,$filename,$path,$revision,$year,$published,$sort,$id]);
                $entityId = $id;
                $action = 'document.update';
                $pdo->prepare('DELETE FROM document_links WHERE document_id=?')->execute([$id]);
            } else {
                $s = $pdo->prepare('INSERT INTO documents(document_type_id,title,filename,file_path,revision,document_year,published,sort_order) VALUES(?,?,?,?,?,?,?,?)');
                $s->execute([$typeId,$title,$filename,$path,$revision,$year,$published,$sort]);
                $entityId = (int)$pdo->lastInsertId();
                $action = 'document.create';
            }
            $l = $pdo->prepare('INSERT INTO document_links(document_id,category_id,product_id,model_id) VALUES(?,?,?,?)');
            $l->execute([$entityId,$categoryId,$productId,$modelId]);
            $pdo->commit();
            if ($uploaded && $oldPath && $oldPath !== $path) Upload::removeManaged($oldPath);
            Audit::log($action, 'document', $entityId, ['title'=>$title,'file_path'=>$path]);
        } catch (\Throwable $e) {
            $pdo->rollBack();
            if ($uploaded) Upload::removeManaged($uploaded['path']);
            throw $e;
        }

        header('Location: /admin/documents');
        exit;
    }

    /** @return array{0:array,1:array,2:array,3:array} */
    private static function formOptions(PDO $pdo): array
    {
        return [
            $pdo->query('SELECT id,name FROM document_types WHERE active=1 ORDER BY sort_order,name')->fetchAll(PDO::FETCH_ASSOC),
            $pdo->query('SELECT id,name FROM product_categories ORDER BY sort_order,name')->fetchAll(PDO::FETCH_ASSOC),
            $pdo->query('SELECT id,name FROM products ORDER BY name')->fetchAll(PDO::FETCH_ASSOC),
            $pdo->query('SELECT id,code FROM product_models ORDER BY code')->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
}
