<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Audit;
use App\Core\Security;
use App\Core\Validator;
use PDO;

final class EditorialController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $pages=Database::connection()->query('SELECT * FROM editorial_pages ORDER BY sort_order,title')->fetchAll(PDO::FETCH_ASSOC);
        self::view('editorial_pages',['title'=>'Pagine informative','pages'=>$pages]);
    }

    public static function form(): void
    {
        AdminAuth::requireLogin();$id=Validator::int($_GET['id']??0);$pdo=Database::connection();
        $s=$pdo->prepare('SELECT * FROM editorial_pages WHERE id=?');$s->execute([$id]);$page=$s->fetch(PDO::FETCH_ASSOC);if(!$page){http_response_code(404);exit('Pagina non trovata');}
        $sections=$pdo->prepare('SELECT * FROM editorial_sections WHERE page_id=? ORDER BY sort_order,id');$sections->execute([$id]);
        $faqs=$pdo->prepare('SELECT * FROM faq_items WHERE page_id=? ORDER BY sort_order,id');$faqs->execute([$id]);
        $links=$pdo->prepare('SELECT epd.*,d.title FROM editorial_page_documents epd JOIN documents d ON d.id=epd.document_id WHERE epd.page_id=? ORDER BY epd.group_label,epd.sort_order');$links->execute([$id]);
        $documents=$pdo->query('SELECT id,title FROM documents ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);
        self::view('editorial_page_form',['title'=>'Modifica pagina','page'=>$page,'sections'=>$sections->fetchAll(PDO::FETCH_ASSOC),'faqs'=>$faqs->fetchAll(PDO::FETCH_ASSOC),'links'=>$links->fetchAll(PDO::FETCH_ASSOC),'documents'=>$documents]);
    }

    public static function savePage(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();
        $s=$pdo->prepare('UPDATE editorial_pages SET title=?,eyebrow=?,intro=?,body=?,meta_title=?,meta_description=?,published=?,sort_order=? WHERE id=?');
        $s->execute([trim((string)$_POST['title']),trim((string)($_POST['eyebrow']??''))?:null,trim((string)($_POST['intro']??''))?:null,trim((string)($_POST['body']??''))?:null,trim((string)($_POST['meta_title']??''))?:null,trim((string)($_POST['meta_description']??''))?:null,Validator::bool($_POST['published']??0),Validator::int($_POST['sort_order']??0),$id]);
        header('Location:/admin/editorial/form?id='.$id);exit;
    }

    public static function addSection(): void
    {
        AdminAuth::requireLogin();self::csrf();$pageId=Validator::int($_POST['page_id']??0);Database::connection()->prepare('INSERT INTO editorial_sections(page_id,title,anchor_slug,body,sort_order,published) VALUES(?,?,?,?,?,?)')->execute([$pageId,trim((string)$_POST['title']),Validator::slug((string)($_POST['anchor_slug']??''))?:null,trim((string)($_POST['body']??''))?:null,Validator::int($_POST['sort_order']??0),1]);header('Location:/admin/editorial/form?id='.$pageId);exit;
    }

    public static function saveSection(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pageId=Validator::int($_POST['page_id']??0);
        $title=trim((string)($_POST['title']??''));if($id<1||$pageId<1||$title===''){http_response_code(422);exit('Dati sezione non validi');}
        Database::connection()->prepare('UPDATE editorial_sections SET title=?,anchor_slug=?,body=?,sort_order=?,published=? WHERE id=? AND page_id=?')->execute([$title,Validator::slug((string)($_POST['anchor_slug']??''))?:null,trim((string)($_POST['body']??''))?:null,Validator::int($_POST['sort_order']??0),Validator::bool($_POST['published']??0),$id,$pageId]);
        Audit::log('editorial.section.update','editorial_section',$id,['page_id'=>$pageId]);self::back($pageId);
    }

    public static function deleteSection(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pageId=Validator::int($_POST['page_id']??0);
        Database::connection()->prepare('DELETE FROM editorial_sections WHERE id=? AND page_id=?')->execute([$id,$pageId]);
        Audit::log('editorial.section.delete','editorial_section',$id,['page_id'=>$pageId]);self::back($pageId);
    }

    public static function addFaq(): void
    {
        AdminAuth::requireLogin();self::csrf();$pageId=Validator::int($_POST['page_id']??0);Database::connection()->prepare('INSERT INTO faq_items(page_id,question,answer,sort_order,published) VALUES(?,?,?,?,1)')->execute([$pageId,trim((string)$_POST['question']),trim((string)$_POST['answer']),Validator::int($_POST['sort_order']??0)]);header('Location:/admin/editorial/form?id='.$pageId);exit;
    }

    public static function saveFaq(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pageId=Validator::int($_POST['page_id']??0);
        $question=trim((string)($_POST['question']??''));$answer=trim((string)($_POST['answer']??''));
        if($id<1||$pageId<1||$question===''||$answer===''){http_response_code(422);exit('Dati FAQ non validi');}
        Database::connection()->prepare('UPDATE faq_items SET question=?,answer=?,sort_order=?,published=? WHERE id=? AND page_id=?')->execute([$question,$answer,Validator::int($_POST['sort_order']??0),Validator::bool($_POST['published']??0),$id,$pageId]);
        Audit::log('editorial.faq.update','faq_item',$id,['page_id'=>$pageId]);self::back($pageId);
    }

    public static function deleteFaq(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pageId=Validator::int($_POST['page_id']??0);
        Database::connection()->prepare('DELETE FROM faq_items WHERE id=? AND page_id=?')->execute([$id,$pageId]);
        Audit::log('editorial.faq.delete','faq_item',$id,['page_id'=>$pageId]);self::back($pageId);
    }

    public static function addDocument(): void
    {
        AdminAuth::requireLogin();self::csrf();$pageId=Validator::int($_POST['page_id']??0);$documentId=Validator::int($_POST['document_id']??0);Database::connection()->prepare('INSERT INTO editorial_page_documents(page_id,document_id,group_label,label,sort_order,published) VALUES(?,?,?,?,?,1)')->execute([$pageId,$documentId,trim((string)($_POST['group_label']??''))?:null,trim((string)($_POST['label']??''))?:null,Validator::int($_POST['sort_order']??0)]);header('Location:/admin/editorial/form?id='.$pageId);exit;
    }

    public static function deleteDocument(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pageId=Validator::int($_POST['page_id']??0);
        Database::connection()->prepare('DELETE FROM editorial_page_documents WHERE id=? AND page_id=?')->execute([$id,$pageId]);
        Audit::log('editorial.document.unlink','editorial_page_document',$id,['page_id'=>$pageId]);self::back($pageId);
    }

    private static function back(int $pageId):void{header('Location:/admin/editorial/form?id='.$pageId);exit;}

    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/'.$file.'.php';}
}
