<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use App\Services\GalleryContent;
use PDO;

final class GalleryContentController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        GalleryContent::ensureSchema();
        $pdo = Database::connection();
        $sections = GalleryContent::sections();
        $items = GalleryContent::items();
        $section = ['id'=>0,'section_key'=>'','eyebrow'=>'','title'=>'','description'=>'','layout_type'=>'cards','published'=>1,'sort_order'=>0];
        $item = ['id'=>0,'section_id'=>(int)($sections[0]['id']??0),'media_type'=>'image','title'=>'','description'=>'','media_url'=>'','link_url'=>'','link_label'=>'','published'=>1,'sort_order'=>0];
        $editSection = Validator::int($_GET['section']??0);
        $editItem = Validator::int($_GET['item']??0);
        if ($editSection) { $s=$pdo->prepare('SELECT * FROM gallery_sections WHERE id=?');$s->execute([$editSection]);$section=$s->fetch(PDO::FETCH_ASSOC)?:$section; }
        if ($editItem) { $s=$pdo->prepare('SELECT * FROM gallery_content_items WHERE id=?');$s->execute([$editItem]);$item=$s->fetch(PDO::FETCH_ASSOC)?:$item; }
        self::view(['title'=>'Contenuti Galleria','sections'=>$sections,'items'=>$items,'section'=>$section,'item'=>$item,'errors'=>[]]);
    }

    public static function saveSection(): void
    {
        AdminAuth::requireLogin(); self::csrf(); GalleryContent::ensureSchema();
        $errors=[];$id=Validator::int($_POST['id']??0);$key=self::slug((string)($_POST['section_key']??''));
        $title=Validator::requiredString($_POST['title']??'','Titolo',220,$errors);$eyebrow=trim((string)($_POST['eyebrow']??''));$description=trim((string)($_POST['description']??''));
        $layout=(string)($_POST['layout_type']??'cards');if(!in_array($layout,['cards','split','split-reverse','photos','videos'],true))$errors[]='Layout non valido.';
        if($key===''||strlen($key)>80)$errors[]='Chiave sezione non valida.';if(mb_strlen($eyebrow)>160)$errors[]='Etichetta troppo lunga.';
        if($errors){http_response_code(422);exit(implode(' ',array_map('htmlspecialchars',$errors)));}
        $pdo=Database::connection();$data=[$key,$eyebrow?:null,$title,$description?:null,$layout,Validator::bool($_POST['published']??0),Validator::int($_POST['sort_order']??0)];
        if($id){$data[]=$id;$pdo->prepare('UPDATE gallery_sections SET section_key=?,eyebrow=?,title=?,description=?,layout_type=?,published=?,sort_order=? WHERE id=?')->execute($data);$action='gallery.section.update';}
        else{$pdo->prepare('INSERT INTO gallery_sections(section_key,eyebrow,title,description,layout_type,published,sort_order) VALUES(?,?,?,?,?,?,?)')->execute($data);$id=(int)$pdo->lastInsertId();$action='gallery.section.create';}
        Audit::log($action,'gallery_section',$id,['key'=>$key]);self::redirect();
    }

    public static function saveItem(): void
    {
        AdminAuth::requireLogin(); self::csrf(); GalleryContent::ensureSchema();
        $errors=[];$id=Validator::int($_POST['id']??0);$sectionId=Validator::int($_POST['section_id']??0);$type=(string)($_POST['media_type']??'image');
        $title=Validator::requiredString($_POST['title']??'','Titolo',255,$errors);$description=trim((string)($_POST['description']??''));$media=trim((string)($_POST['media_url']??''));
        $link=trim((string)($_POST['link_url']??''));$label=trim((string)($_POST['link_label']??''));
        if(!in_array($type,['image','youtube'],true))$errors[]='Tipo contenuto non valido.';if($media==='')$errors[]=$type==='youtube'?'Inserisci l’ID YouTube.':'Inserisci l’URL dell’immagine.';
        $pdo=Database::connection();$s=$pdo->prepare('SELECT COUNT(*) FROM gallery_sections WHERE id=?');$s->execute([$sectionId]);if(!(int)$s->fetchColumn())$errors[]='Sezione non valida.';
        if($errors){http_response_code(422);exit(implode(' ',array_map('htmlspecialchars',$errors)));}
        $data=[$sectionId,$type,$title,$description?:null,$media,$link?:null,$label?:null,Validator::bool($_POST['published']??0),Validator::int($_POST['sort_order']??0)];
        if($id){$data[]=$id;$pdo->prepare('UPDATE gallery_content_items SET section_id=?,media_type=?,title=?,description=?,media_url=?,link_url=?,link_label=?,published=?,sort_order=? WHERE id=?')->execute($data);$action='gallery.content.update';}
        else{$pdo->prepare('INSERT INTO gallery_content_items(section_id,media_type,title,description,media_url,link_url,link_label,published,sort_order) VALUES(?,?,?,?,?,?,?,?,?)')->execute($data);$id=(int)$pdo->lastInsertId();$action='gallery.content.create';}
        Audit::log($action,'gallery_content_item',$id,['section_id'=>$sectionId]);self::redirect();
    }

    public static function deleteItem(): void
    {
        AdminAuth::requireLogin(); self::csrf(); GalleryContent::ensureSchema();$id=Validator::int($_POST['id']??0);
        $s=Database::connection()->prepare('DELETE FROM gallery_content_items WHERE id=?');$s->execute([$id]);Audit::log('gallery.content.delete','gallery_content_item',$id,[]);self::redirect();
    }

    private static function view(array $data): void { extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/idemaclima/admin/content_gallery_manager.php'; }
    private static function csrf(): void { if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');} }
    private static function redirect(): never { header('Location:/idemaclima/admin/content/gallery/manage');exit; }
    private static function slug(string $value): string { $ascii=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',strtolower(trim($value)))?:$value;return trim((string)preg_replace('/[^a-z0-9]+/','-',$ascii),'-'); }
}
