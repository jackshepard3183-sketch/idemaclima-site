<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

final class AssistanceController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $rows=Database::connection()->query('SELECT ar.*,d.title document_title FROM assistance_resources ar LEFT JOIN documents d ON d.id=ar.document_id ORDER BY ar.section,ar.sort_order,ar.id')->fetchAll(PDO::FETCH_ASSOC);
        self::view('assistance_resources',['title'=>'Assistenza - Risorse','rows'=>$rows]);
    }

    public static function form(): void
    {
        AdminAuth::requireLogin();$pdo=Database::connection();$id=Validator::int($_GET['id']??0);
        $resource=['id'=>0,'section'=>'warranty','label'=>'','document_id'=>'','external_url'=>'','sort_order'=>0,'published'=>1];
        if($id){$s=$pdo->prepare('SELECT * FROM assistance_resources WHERE id=?');$s->execute([$id]);$resource=$s->fetch(PDO::FETCH_ASSOC)?:$resource;}
        $documents=$pdo->query('SELECT d.id,d.title FROM documents d WHERE d.published=1 ORDER BY d.title')->fetchAll(PDO::FETCH_ASSOC);
        self::view('assistance_resource_form',['title'=>'Risorsa Assistenza','resource'=>$resource,'documents'=>$documents,'errors'=>[]]);
    }

    public static function save(): void
    {
        AdminAuth::requireLogin();self::csrf();$errors=[];$pdo=Database::connection();$id=Validator::int($_POST['id']??0);
        $section=in_array($_POST['section']??'', ['warranty','error_code'],true)?$_POST['section']:'warranty';
        $label=Validator::requiredString($_POST['label']??'','Etichetta',220,$errors);
        $documentId=Validator::int($_POST['document_id']??0)?:null;
        $external=trim((string)($_POST['external_url']??''))?:null;
        if($documentId){$s=$pdo->prepare('SELECT COUNT(*) FROM documents WHERE id=?');$s->execute([$documentId]);if(!(int)$s->fetchColumn())$errors[]='Documento non valido.';}
        if($external && !filter_var($external,FILTER_VALIDATE_URL))$errors[]='URL esterno non valido.';
        if(!$documentId && !$external)$errors[]='Seleziona un documento oppure inserisci un URL esterno.';
        $sort=Validator::int($_POST['sort_order']??0);$published=Validator::bool($_POST['published']??0);
        $resource=['id'=>$id,'section'=>$section,'label'=>$label,'document_id'=>$documentId,'external_url'=>$external,'sort_order'=>$sort,'published'=>$published];
        if($errors){$documents=$pdo->query('SELECT d.id,d.title FROM documents d WHERE d.published=1 ORDER BY d.title')->fetchAll(PDO::FETCH_ASSOC);self::view('assistance_resource_form',['title'=>'Risorsa Assistenza','resource'=>$resource,'documents'=>$documents,'errors'=>$errors]);return;}
        if($id){$s=$pdo->prepare('UPDATE assistance_resources SET section=?,label=?,document_id=?,external_url=?,sort_order=?,published=? WHERE id=?');$s->execute([$section,$label,$documentId,$external,$sort,$published,$id]);}
        else{$s=$pdo->prepare('INSERT INTO assistance_resources(section,label,document_id,external_url,sort_order,published) VALUES(?,?,?,?,?,?)');$s->execute([$section,$label,$documentId,$external,$sort,$published]);}
        header('Location:/admin/assistance');exit;
    }

    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/'.$file.'.php';}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
}
