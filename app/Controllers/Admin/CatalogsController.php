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
use Throwable;

final class CatalogsController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $rows = Database::connection()->query(
            'SELECT c.*,d.title document_title
             FROM catalogs c
             LEFT JOIN documents d ON d.id=c.document_id
             ORDER BY c.sort_order,c.title'
        )->fetchAll(PDO::FETCH_ASSOC);
        self::view('content_catalogs',['title'=>'Cataloghi','rows'=>$rows]);
    }

    public static function form(): void
    {
        AdminAuth::requireLogin();
        $pdo = Database::connection();
        $id = Validator::int($_GET['id'] ?? 0);
        $row = ['id'=>0,'title'=>'','slug'=>'','description'=>'','cover_image'=>'','document_id'=>'','pdf_path'=>'','document_year'=>'','published'=>1,'sort_order'=>0];
        if ($id) {
            $stmt = $pdo->prepare('SELECT * FROM catalogs WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$found) { http_response_code(404); exit('Catalogo non trovato'); }
            $row = $found;
        }
        self::view('content_catalog_form',[
            'title'=>'Catalogo',
            'row'=>$row,
            'documents'=>self::documents($pdo),
            'errors'=>[],
        ]);
    }

    public static function save(): void
    {
        AdminAuth::requireLogin();
        self::csrf();
        $pdo = Database::connection();
        $errors = [];
        $id = Validator::int($_POST['id'] ?? 0);
        $title = Validator::requiredString($_POST['title'] ?? '', 'Titolo', 220, $errors);
        $slug = self::slugify(trim((string)($_POST['slug'] ?? '')) ?: $title);
        if ($slug === '') $errors[] = 'Slug non valido.';
        $description = trim((string)($_POST['description'] ?? ''));
        if (mb_strlen($description) > 5000) $errors[] = 'Descrizione troppo lunga.';
        $year = Validator::int($_POST['document_year'] ?? 0) ?: null;
        if ($year !== null && ($year < 1990 || $year > ((int)date('Y') + 1))) $errors[] = 'Anno documento non valido.';
        $published = Validator::bool($_POST['published'] ?? 0);
        $sort = Validator::int($_POST['sort_order'] ?? 0);
        $documentId = Validator::int($_POST['document_id'] ?? 0) ?: null;

        $existing = ['cover_image'=>null,'pdf_path'=>null,'document_id'=>null];
        if ($id) {
            $stmt = $pdo->prepare('SELECT cover_image,pdf_path,document_id FROM catalogs WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$found) { http_response_code(404); exit('Catalogo non trovato'); }
            $existing = $found;
        }

        if ($documentId !== null) {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM documents WHERE id=? AND published=1');
            $stmt->execute([$documentId]);
            if ((int)$stmt->fetchColumn() !== 1) $errors[] = 'Documento canonico non valido o non pubblicato.';
        }

        $cover = Upload::contentImage('cover_image','catalogs',$errors);
        $pdf = Upload::contentPdf('pdf_file','catalogs',$errors);
        if ($documentId !== null && $pdf !== null) $errors[] = 'Seleziona un documento canonico oppure carica un PDF, non entrambi.';

        $coverPath = $cover['path'] ?? $existing['cover_image'];
        $pdfPath = $pdf['path'] ?? $existing['pdf_path'];
        if ($documentId !== null) $pdfPath = null;
        if ($pdf !== null) $documentId = null;
        if ($documentId === null && empty($pdfPath)) $errors[] = 'Seleziona un documento canonico oppure carica il PDF del catalogo.';

        $row = [
            'id'=>$id,'title'=>$title,'slug'=>$slug,'description'=>$description,'cover_image'=>$coverPath,
            'document_id'=>$documentId,'pdf_path'=>$pdfPath,'document_year'=>$year,'published'=>$published,'sort_order'=>$sort,
        ];
        if ($errors) {
            if ($cover) Upload::removeManaged($cover['path']);
            if ($pdf) Upload::removeManaged($pdf['path']);
            self::view('content_catalog_form',['title'=>'Catalogo','row'=>$row,'documents'=>self::documents($pdo),'errors'=>array_values(array_unique($errors))]);
            return;
        }

        try {
            $pdo->beginTransaction();
            $dup = $pdo->prepare('SELECT id FROM catalogs WHERE slug=? AND id<>? LIMIT 1');
            $dup->execute([$slug,$id]);
            if ($dup->fetchColumn() !== false) throw new \RuntimeException('Slug già utilizzato da un altro catalogo.');

            if ($id) {
                $stmt = $pdo->prepare(
                    'UPDATE catalogs SET title=?,slug=?,description=?,cover_image=?,document_id=?,pdf_path=?,document_year=?,published=?,sort_order=? WHERE id=?'
                );
                $stmt->execute([$title,$slug,$description?:null,$coverPath?:null,$documentId,$pdfPath,$year,$published,$sort,$id]);
                $entityId = $id;
                $action = 'catalog.update';
            } else {
                $stmt = $pdo->prepare(
                    'INSERT INTO catalogs(title,slug,description,cover_image,document_id,pdf_path,document_year,published,sort_order) VALUES(?,?,?,?,?,?,?,?,?)'
                );
                $stmt->execute([$title,$slug,$description?:null,$coverPath?:null,$documentId,$pdfPath,$year,$published,$sort]);
                $entityId = (int)$pdo->lastInsertId();
                $action = 'catalog.create';
            }
            Audit::log($action,'catalog',$entityId,['slug'=>$slug,'document_id'=>$documentId,'year'=>$year]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($cover) Upload::removeManaged($cover['path']);
            if ($pdf) Upload::removeManaged($pdf['path']);
            $errors[] = $e instanceof \RuntimeException ? $e->getMessage() : 'Impossibile salvare il catalogo.';
            self::view('content_catalog_form',['title'=>'Catalogo','row'=>$row,'documents'=>self::documents($pdo),'errors'=>$errors]);
            return;
        }

        if ($cover && !empty($existing['cover_image']) && $existing['cover_image'] !== $cover['path']) Upload::removeManaged((string)$existing['cover_image']);
        if ($pdf && !empty($existing['pdf_path']) && $existing['pdf_path'] !== $pdf['path']) Upload::removeManaged((string)$existing['pdf_path']);
        if ($documentId !== null && !empty($existing['pdf_path'])) Upload::removeManaged((string)$existing['pdf_path']);

        header('Location:/idemaclima/admin/content/catalogs');
        exit;
    }

    public static function duplicate(): void
    {
        AdminAuth::requireLogin(); self::csrf();
        $pdo=Database::connection(); $id=Validator::int($_POST['id']??0);
        $s=$pdo->prepare('SELECT * FROM catalogs WHERE id=? LIMIT 1');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);
        if(!$row){http_response_code(404);exit('Catalogo non trovato');}
        $slug=self::slugify((string)$row['slug'].'-copia');$n=2;
        while(true){$q=$pdo->prepare('SELECT 1 FROM catalogs WHERE slug=?');$q->execute([$slug]);if(!$q->fetchColumn())break;$slug=self::slugify((string)$row['slug'].'-copia-'.$n++);}
        $cover=Upload::duplicateManaged($row['cover_image']??null);$pdf=!empty($row['document_id'])?null:Upload::duplicateManaged($row['pdf_path']??null);
        $q=$pdo->prepare('INSERT INTO catalogs(title,slug,description,cover_image,document_id,pdf_path,document_year,published,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');
        $q->execute(['Copia di '.$row['title'],$slug,$row['description'],$cover,$row['document_id'],$pdf,$row['document_year'],0,(int)$row['sort_order']+1]);
        $newId=(int)$pdo->lastInsertId();Audit::log('catalog.duplicate','catalog',$newId,['source_id'=>$id]);
        header('Location:/idemaclima/admin/content/catalogs/form?id='.$newId);exit;
    }

    private static function documents(PDO $pdo): array
    {
        return $pdo->query('SELECT id,title,filename FROM documents WHERE published=1 ORDER BY title,filename')->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function view(string $file,array $data): void
    {
        extract($data,EXTR_SKIP);
        $user=AdminAuth::user();
        $csrf=Security::csrfToken();
        require dirname(__DIR__,2).'/Views/admin/'.$file.'.php';
    }

    private static function csrf(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
    }

    private static function slugify(string $value): string
    {
        $ascii = iconv('UTF-8','ASCII//TRANSLIT//IGNORE',strtolower(trim($value))) ?: $value;
        return trim((string)preg_replace('/[^a-z0-9]+/','-',$ascii),'-');
    }
}
