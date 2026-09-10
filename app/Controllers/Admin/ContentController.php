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
use PDOException;
use Throwable;

final class ContentController
{
    public static function catalogs(): void { AdminAuth::requireLogin(); $rows=Database::connection()->query('SELECT * FROM catalogs ORDER BY sort_order,title')->fetchAll(PDO::FETCH_ASSOC); self::view('content_catalogs',['title'=>'Cataloghi','rows'=>$rows]); }
    public static function catalogForm(): void { AdminAuth::requireLogin(); $id=Validator::int($_GET['id']??0); $row=['id'=>0,'title'=>'','slug'=>'','description'=>'','cover_image'=>'','pdf_path'=>'','document_year'=>'','published'=>1,'sort_order'=>0]; if($id){$s=Database::connection()->prepare('SELECT * FROM catalogs WHERE id=?');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC)?:$row;} self::view('content_catalog_form',['title'=>'Catalogo','row'=>$row,'errors'=>[]]); }
    public static function saveCatalog(): void { AdminAuth::requireLogin(); self::csrf(); $errors=[];$id=Validator::int($_POST['id']??0);$title=Validator::requiredString($_POST['title']??'','Titolo',220,$errors);$slug=trim((string)($_POST['slug']??''))?:self::slugify($title);$description=trim((string)($_POST['description']??''));$year=Validator::int($_POST['document_year']??0)?:null;$published=Validator::bool($_POST['published']??0);$sort=Validator::int($_POST['sort_order']??0);$pdo=Database::connection();$existing=['cover_image'=>'','pdf_path'=>''];if($id){$s=$pdo->prepare('SELECT cover_image,pdf_path FROM catalogs WHERE id=?');$s->execute([$id]);$existing=$s->fetch(PDO::FETCH_ASSOC)?:$existing;}$cover=Upload::contentImage('cover_image','catalogs',$errors);$pdf=Upload::contentPdf('pdf_file','catalogs',$errors);$coverPath=$cover['path']??$existing['cover_image'];$pdfPath=$pdf['path']??$existing['pdf_path'];if($pdfPath==='')$errors[]='Carica il PDF del catalogo.';$row=['id'=>$id,'title'=>$title,'slug'=>$slug,'description'=>$description,'cover_image'=>$coverPath,'pdf_path'=>$pdfPath,'document_year'=>$year,'published'=>$published,'sort_order'=>$sort];if($errors){if($cover)Upload::removeManaged($cover['path']);if($pdf)Upload::removeManaged($pdf['path']);self::view('content_catalog_form',['title'=>'Catalogo','row'=>$row,'errors'=>$errors]);return;}if($id){$s=$pdo->prepare('UPDATE catalogs SET title=?,slug=?,description=?,cover_image=?,pdf_path=?,document_year=?,published=?,sort_order=? WHERE id=?');$s->execute([$title,$slug,$description?:null,$coverPath?:null,$pdfPath,$year,$published,$sort,$id]);}else{$s=$pdo->prepare('INSERT INTO catalogs(title,slug,description,cover_image,pdf_path,document_year,published,sort_order) VALUES(?,?,?,?,?,?,?,?)');$s->execute([$title,$slug,$description?:null,$coverPath?:null,$pdfPath,$year,$published,$sort]);}header('Location:/idemaclima/admin/content/catalogs');exit; }

    public static function albums(): void
    {
        AdminAuth::requireLogin();
        $rows=Database::connection()->query('SELECT a.*,COUNT(i.id) image_count FROM gallery_albums a LEFT JOIN gallery_images i ON i.album_id=a.id WHERE a.archived_at IS NULL GROUP BY a.id ORDER BY a.sort_order,a.title')->fetchAll(PDO::FETCH_ASSOC);
        self::view('content_albums',['title'=>'Galleria','rows'=>$rows]);
    }

    public static function albumForm(): void
    {
        AdminAuth::requireLogin();
        $id=Validator::int($_GET['id']??0);$pdo=Database::connection();
        $row=['id'=>0,'title'=>'','slug'=>'','description'=>'','cover_image'=>'','published'=>1,'sort_order'=>0];$images=[];
        if($id){
            $s=$pdo->prepare('SELECT * FROM gallery_albums WHERE id=? AND archived_at IS NULL');$s->execute([$id]);$found=$s->fetch(PDO::FETCH_ASSOC);
            if(!$found){self::notFound('Album non trovato');}
            $row=$found;
            $s=$pdo->prepare('SELECT * FROM gallery_images WHERE album_id=? ORDER BY sort_order,id');$s->execute([$id]);$images=$s->fetchAll(PDO::FETCH_ASSOC);
        }
        self::view('content_album_form',['title'=>'Album','row'=>$row,'images'=>$images,'errors'=>[]]);
    }

    public static function saveAlbum(): void
    {
        AdminAuth::requireLogin();self::csrf();$errors=[];$id=Validator::int($_POST['id']??0);$pdo=Database::connection();
        $title=Validator::requiredString($_POST['title']??'','Titolo',220,$errors);$slug=self::normalizeSlug((string)($_POST['slug']??''),$title,$errors);
        $desc=trim((string)($_POST['description']??''));if(mb_strlen($desc)>5000)$errors[]='Descrizione troppo lunga.';
        $published=Validator::bool($_POST['published']??0);$sort=Validator::int($_POST['sort_order']??0);$existing='';
        if($id){$s=$pdo->prepare('SELECT cover_image FROM gallery_albums WHERE id=? AND archived_at IS NULL');$s->execute([$id]);$value=$s->fetchColumn();if($value===false)self::notFound('Album non trovato');$existing=(string)($value?:'');}
        $cover=Upload::contentImage('cover_image','gallery',$errors);$coverPath=$cover['path']??$existing;
        $row=['id'=>$id,'title'=>$title,'slug'=>$slug,'description'=>$desc,'cover_image'=>$coverPath,'published'=>$published,'sort_order'=>$sort];
        if($errors){if($cover)Upload::removeManaged($cover['path']);self::view('content_album_form',['title'=>'Album','row'=>$row,'images'=>$id?self::galleryImages($pdo,$id):[],'errors'=>$errors]);return;}
        try{
            $pdo->beginTransaction();
            if($id){$s=$pdo->prepare('UPDATE gallery_albums SET title=?,slug=?,description=?,cover_image=?,published=?,sort_order=? WHERE id=? AND archived_at IS NULL');$s->execute([$title,$slug,$desc?:null,$coverPath?:null,$published,$sort,$id]);$action='gallery.album.update';}
            else{$s=$pdo->prepare('INSERT INTO gallery_albums(title,slug,description,cover_image,published,sort_order) VALUES(?,?,?,?,?,?)');$s->execute([$title,$slug,$desc?:null,$coverPath?:null,$published,$sort]);$id=(int)$pdo->lastInsertId();$action='gallery.album.create';}
            $pdo->commit();
            Audit::log($action,'gallery_album',$id,['slug'=>$slug,'published'=>$published]);
            if($cover && $existing!=='' && $existing!==$coverPath)Upload::removeManaged($existing);
        }catch(PDOException $e){
            if($pdo->inTransaction())$pdo->rollBack();if($cover)Upload::removeManaged($cover['path']);
            if((string)$e->getCode()==='23000'){$errors[]='Slug già utilizzato da un altro album.';self::view('content_album_form',['title'=>'Album','row'=>$row,'images'=>$id?self::galleryImages($pdo,$id):[],'errors'=>$errors]);return;}throw $e;
        }
        header('Location:/idemaclima/admin/content/gallery/form?id='.$id);exit;
    }

    public static function archiveAlbum(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();
        $s=$pdo->prepare('UPDATE gallery_albums SET archived_at=NOW(),published=0 WHERE id=? AND archived_at IS NULL');$s->execute([$id]);if($s->rowCount()!==1)self::notFound('Album non trovato');
        Audit::log('gallery.album.archive','gallery_album',$id,[]);header('Location:/idemaclima/admin/content/gallery');exit;
    }

    public static function addGalleryImage(): void
    {
        AdminAuth::requireLogin();self::csrf();$errors=[];$albumId=Validator::int($_POST['album_id']??0);$pdo=Database::connection();self::assertAlbum($pdo,$albumId);
        $alt=trim((string)($_POST['alt_text']??''));$caption=trim((string)($_POST['caption']??''));if(mb_strlen($alt)>255)$errors[]='Testo alternativo troppo lungo.';if(mb_strlen($caption)>500)$errors[]='Didascalia troppo lunga.';
        $upload=Upload::contentImage('image_file','gallery',$errors);if(!$upload)$errors[]='Seleziona un’immagine valida.';
        if($errors){if($upload)Upload::removeManaged($upload['path']);self::redirectAlbum($albumId);}
        try{$s=$pdo->prepare('INSERT INTO gallery_images(album_id,image_path,alt_text,caption,published,sort_order) VALUES(?,?,?,?,?,?)');$s->execute([$albumId,$upload['path'],$alt?:null,$caption?:null,Validator::bool($_POST['published']??1),Validator::int($_POST['sort_order']??0)]);$imageId=(int)$pdo->lastInsertId();Audit::log('gallery.image.create','gallery_image',$imageId,['album_id'=>$albumId]);}catch(Throwable $e){Upload::removeManaged($upload['path']);throw $e;}
        self::redirectAlbum($albumId);
    }

    public static function updateGalleryImage(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();$s=$pdo->prepare('SELECT * FROM gallery_images WHERE id=?');$s->execute([$id]);$img=$s->fetch(PDO::FETCH_ASSOC);if(!$img)self::notFound('Immagine non trovata');
        $alt=trim((string)($_POST['alt_text']??''));$caption=trim((string)($_POST['caption']??''));if(mb_strlen($alt)>255||mb_strlen($caption)>500){self::redirectAlbum((int)$img['album_id']);}
        $pdo->prepare('UPDATE gallery_images SET alt_text=?,caption=?,published=?,sort_order=? WHERE id=?')->execute([$alt?:null,$caption?:null,Validator::bool($_POST['published']??0),Validator::int($_POST['sort_order']??0),$id]);
        Audit::log('gallery.image.update','gallery_image',$id,['album_id'=>(int)$img['album_id']]);self::redirectAlbum((int)$img['album_id']);
    }

    public static function deleteGalleryImage(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();$s=$pdo->prepare('SELECT album_id,image_path FROM gallery_images WHERE id=?');$s->execute([$id]);$img=$s->fetch(PDO::FETCH_ASSOC);if(!$img)self::notFound('Immagine non trovata');
        $pdo->prepare('DELETE FROM gallery_images WHERE id=?')->execute([$id]);Audit::log('gallery.image.delete','gallery_image',$id,['album_id'=>(int)$img['album_id']]);Upload::removeManaged((string)$img['image_path']);self::redirectAlbum((int)$img['album_id']);
    }

    public static function references(): void
    {
        AdminAuth::requireLogin();$rows=Database::connection()->query('SELECT r.*,COUNT(i.id) image_count FROM references_projects r LEFT JOIN reference_images i ON i.reference_id=r.id WHERE r.archived_at IS NULL GROUP BY r.id ORDER BY r.sort_order,r.title')->fetchAll(PDO::FETCH_ASSOC);self::view('content_references',['title'=>'Referenze','rows'=>$rows]);
    }

    public static function referenceForm(): void
    {
        AdminAuth::requireLogin();$id=Validator::int($_GET['id']??0);$pdo=Database::connection();$row=['id'=>0,'title'=>'','slug'=>'','location'=>'','project_year'=>'','short_description'=>'','description'=>'','cover_image'=>'','published'=>1,'sort_order'=>0];$images=[];
        if($id){$s=$pdo->prepare('SELECT * FROM references_projects WHERE id=? AND archived_at IS NULL');$s->execute([$id]);$found=$s->fetch(PDO::FETCH_ASSOC);if(!$found)self::notFound('Referenza non trovata');$row=$found;$s=$pdo->prepare('SELECT * FROM reference_images WHERE reference_id=? ORDER BY sort_order,id');$s->execute([$id]);$images=$s->fetchAll(PDO::FETCH_ASSOC);}
        self::view('content_reference_form',['title'=>'Referenza','row'=>$row,'images'=>$images,'errors'=>[]]);
    }

    public static function saveReference(): void
    {
        AdminAuth::requireLogin();self::csrf();$errors=[];$id=Validator::int($_POST['id']??0);$pdo=Database::connection();$title=Validator::requiredString($_POST['title']??'','Titolo',220,$errors);$slug=self::normalizeSlug((string)($_POST['slug']??''),$title,$errors);
        $location=trim((string)($_POST['location']??''));if(mb_strlen($location)>190)$errors[]='Località troppo lunga.';$year=Validator::int($_POST['project_year']??0)?:null;if($year!==null&&($year<1900||$year>2100))$errors[]='Anno progetto non valido.';
        $short=trim((string)($_POST['short_description']??''));$desc=trim((string)($_POST['description']??''));if(mb_strlen($short)>3000)$errors[]='Descrizione breve troppo lunga.';if(mb_strlen($desc)>20000)$errors[]='Descrizione troppo lunga.';
        $published=Validator::bool($_POST['published']??0);$sort=Validator::int($_POST['sort_order']??0);$existing='';if($id){$s=$pdo->prepare('SELECT cover_image FROM references_projects WHERE id=? AND archived_at IS NULL');$s->execute([$id]);$value=$s->fetchColumn();if($value===false)self::notFound('Referenza non trovata');$existing=(string)($value?:'');}
        $cover=Upload::contentImage('cover_image','references',$errors);$coverPath=$cover['path']??$existing;$row=['id'=>$id,'title'=>$title,'slug'=>$slug,'location'=>$location,'project_year'=>$year,'short_description'=>$short,'description'=>$desc,'cover_image'=>$coverPath,'published'=>$published,'sort_order'=>$sort];
        if($errors){if($cover)Upload::removeManaged($cover['path']);self::view('content_reference_form',['title'=>'Referenza','row'=>$row,'images'=>$id?self::referenceImages($pdo,$id):[],'errors'=>$errors]);return;}
        try{$pdo->beginTransaction();if($id){$pdo->prepare('UPDATE references_projects SET title=?,slug=?,location=?,project_year=?,short_description=?,description=?,cover_image=?,published=?,sort_order=? WHERE id=? AND archived_at IS NULL')->execute([$title,$slug,$location?:null,$year,$short?:null,$desc?:null,$coverPath?:null,$published,$sort,$id]);$action='reference.update';}else{$pdo->prepare('INSERT INTO references_projects(title,slug,location,project_year,short_description,description,cover_image,published,sort_order) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$title,$slug,$location?:null,$year,$short?:null,$desc?:null,$coverPath?:null,$published,$sort]);$id=(int)$pdo->lastInsertId();$action='reference.create';}$pdo->commit();Audit::log($action,'reference_project',$id,['slug'=>$slug,'published'=>$published]);if($cover&&$existing!==''&&$existing!==$coverPath)Upload::removeManaged($existing);}catch(PDOException $e){if($pdo->inTransaction())$pdo->rollBack();if($cover)Upload::removeManaged($cover['path']);if((string)$e->getCode()==='23000'){$errors[]='Slug già utilizzato da un’altra referenza.';self::view('content_reference_form',['title'=>'Referenza','row'=>$row,'images'=>$id?self::referenceImages($pdo,$id):[],'errors'=>$errors]);return;}throw $e;}
        header('Location:/idemaclima/admin/content/references/form?id='.$id);exit;
    }

    public static function archiveReference(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();$s=$pdo->prepare('UPDATE references_projects SET archived_at=NOW(),published=0 WHERE id=? AND archived_at IS NULL');$s->execute([$id]);if($s->rowCount()!==1)self::notFound('Referenza non trovata');Audit::log('reference.archive','reference_project',$id,[]);header('Location:/idemaclima/admin/content/references');exit;
    }

    public static function duplicateReference(): void
    {
        AdminAuth::requireLogin();self::csrf();$pdo=Database::connection();$id=Validator::int($_POST['id']??0);
        $s=$pdo->prepare('SELECT * FROM references_projects WHERE id=? AND archived_at IS NULL');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row)self::notFound('Referenza non trovata');
        $slug=self::slugify((string)$row['slug'].'-copia');$n=2;while(true){$q=$pdo->prepare('SELECT 1 FROM references_projects WHERE slug=?');$q->execute([$slug]);if(!$q->fetchColumn())break;$slug=self::slugify((string)$row['slug'].'-copia-'.$n++);}
        $pdo->beginTransaction();try{$cover=Upload::duplicateManaged($row['cover_image']??null);$q=$pdo->prepare('INSERT INTO references_projects(title,slug,location,project_year,short_description,description,cover_image,published,sort_order) VALUES(?,?,?,?,?,?,?,?,?)');$q->execute(['Copia di '.$row['title'],$slug,$row['location'],$row['project_year'],$row['short_description'],$row['description'],$cover,0,(int)$row['sort_order']+1]);$newId=(int)$pdo->lastInsertId();$imgs=$pdo->prepare('SELECT * FROM reference_images WHERE reference_id=? ORDER BY sort_order,id');$imgs->execute([$id]);foreach($imgs->fetchAll(PDO::FETCH_ASSOC) as $img){$path=Upload::duplicateManaged($img['image_path']??null);$pdo->prepare('INSERT INTO reference_images(reference_id,image_path,alt_text,caption,published,sort_order) VALUES(?,?,?,?,?,?)')->execute([$newId,$path,$img['alt_text'],$img['caption'],0,$img['sort_order']]);}$pdo->commit();Audit::log('reference.duplicate','reference_project',$newId,['source_id'=>$id]);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
        header('Location:/idemaclima/admin/content/references/form?id='.$newId);exit;
    }

    public static function addReferenceImage(): void
    {
        AdminAuth::requireLogin();self::csrf();$errors=[];$referenceId=Validator::int($_POST['reference_id']??0);$pdo=Database::connection();self::assertReference($pdo,$referenceId);$alt=trim((string)($_POST['alt_text']??''));$caption=trim((string)($_POST['caption']??''));if(mb_strlen($alt)>255)$errors[]='Testo alternativo troppo lungo.';if(mb_strlen($caption)>500)$errors[]='Didascalia troppo lunga.';$upload=Upload::contentImage('image_file','references',$errors);if(!$upload)$errors[]='Seleziona un’immagine valida.';if($errors){if($upload)Upload::removeManaged($upload['path']);self::redirectReference($referenceId);}try{$pdo->prepare('INSERT INTO reference_images(reference_id,image_path,alt_text,caption,published,sort_order) VALUES(?,?,?,?,?,?)')->execute([$referenceId,$upload['path'],$alt?:null,$caption?:null,Validator::bool($_POST['published']??1),Validator::int($_POST['sort_order']??0)]);$imageId=(int)$pdo->lastInsertId();Audit::log('reference.image.create','reference_image',$imageId,['reference_id'=>$referenceId]);}catch(Throwable $e){Upload::removeManaged($upload['path']);throw $e;}self::redirectReference($referenceId);
    }

    public static function updateReferenceImage(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();$s=$pdo->prepare('SELECT * FROM reference_images WHERE id=?');$s->execute([$id]);$img=$s->fetch(PDO::FETCH_ASSOC);if(!$img)self::notFound('Immagine non trovata');$alt=trim((string)($_POST['alt_text']??''));$caption=trim((string)($_POST['caption']??''));if(mb_strlen($alt)>255||mb_strlen($caption)>500)self::redirectReference((int)$img['reference_id']);$pdo->prepare('UPDATE reference_images SET alt_text=?,caption=?,published=?,sort_order=? WHERE id=?')->execute([$alt?:null,$caption?:null,Validator::bool($_POST['published']??0),Validator::int($_POST['sort_order']??0),$id]);Audit::log('reference.image.update','reference_image',$id,['reference_id'=>(int)$img['reference_id']]);self::redirectReference((int)$img['reference_id']);
    }

    public static function deleteReferenceImage(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();$s=$pdo->prepare('SELECT reference_id,image_path FROM reference_images WHERE id=?');$s->execute([$id]);$img=$s->fetch(PDO::FETCH_ASSOC);if(!$img)self::notFound('Immagine non trovata');$pdo->prepare('DELETE FROM reference_images WHERE id=?')->execute([$id]);Audit::log('reference.image.delete','reference_image',$id,['reference_id'=>(int)$img['reference_id']]);Upload::removeManaged((string)$img['image_path']);self::redirectReference((int)$img['reference_id']);
    }

    private static function galleryImages(PDO $pdo,int $id):array{$s=$pdo->prepare('SELECT * FROM gallery_images WHERE album_id=? ORDER BY sort_order,id');$s->execute([$id]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    private static function referenceImages(PDO $pdo,int $id):array{$s=$pdo->prepare('SELECT * FROM reference_images WHERE reference_id=? ORDER BY sort_order,id');$s->execute([$id]);return $s->fetchAll(PDO::FETCH_ASSOC);}
    private static function assertAlbum(PDO $pdo,int $id):void{$s=$pdo->prepare('SELECT COUNT(*) FROM gallery_albums WHERE id=? AND archived_at IS NULL');$s->execute([$id]);if(!(int)$s->fetchColumn())self::notFound('Album non trovato');}
    private static function assertReference(PDO $pdo,int $id):void{$s=$pdo->prepare('SELECT COUNT(*) FROM references_projects WHERE id=? AND archived_at IS NULL');$s->execute([$id]);if(!(int)$s->fetchColumn())self::notFound('Referenza non trovata');}
    private static function redirectAlbum(int $id):never{header('Location:/idemaclima/admin/content/gallery/form?id='.$id);exit;}
    private static function redirectReference(int $id):never{header('Location:/idemaclima/admin/content/references/form?id='.$id);exit;}
    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/idemaclima/admin/'.$file.'.php';}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
    private static function notFound(string $message):never{http_response_code(404);exit($message);}
    private static function normalizeSlug(string $slug,string $fallback,array &$errors):string{$slug=trim($slug);$slug=$slug===''?self::slugify($fallback):self::slugify($slug);if($slug===''||strlen($slug)>240)$errors[]='Slug non valido.';return $slug;}
    private static function slugify(string $v):string{$a=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',strtolower(trim($v)))?:$v;return trim((string)preg_replace('/[^a-z0-9]+/','-',$a),'-');}
}
