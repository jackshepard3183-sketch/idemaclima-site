<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

final class RedirectsController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $rows = Database::connection()->query('SELECT * FROM seo_redirects ORDER BY source_path')->fetchAll(PDO::FETCH_ASSOC);
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__,2).'/Views/admin/redirects.php';
    }

    public static function form(): void
    {
        AdminAuth::requireLogin();
        $id=Validator::int($_GET['id']??0);
        $redirect=['id'=>0,'source_path'=>'','target_path'=>'','status_code'=>301,'enabled'=>1];
        if($id){$s=Database::connection()->prepare('SELECT * FROM seo_redirects WHERE id=?');$s->execute([$id]);$redirect=$s->fetch(PDO::FETCH_ASSOC)?:$redirect;}
        $errors=[];$user=AdminAuth::user();$csrf=Security::csrfToken();
        require dirname(__DIR__,2).'/Views/admin/redirect_form.php';
    }

    public static function save(): void
    {
        AdminAuth::requireLogin();
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        $errors=[];$id=Validator::int($_POST['id']??0);
        $source=self::path((string)($_POST['source_path']??''));
        $target=self::path((string)($_POST['target_path']??''));
        $code=Validator::int($_POST['status_code']??301);
        $enabled=Validator::bool($_POST['enabled']??0);
        if($source===''||$source==='/')$errors[]='Percorso sorgente non valido.';
        if($target===''||$target===$source)$errors[]='Percorso destinazione non valido.';
        if(!in_array($code,[301,302,307,308],true))$errors[]='Codice redirect non valido.';
        $pdo=Database::connection();
        $q=$pdo->prepare('SELECT id FROM seo_redirects WHERE source_path=? AND id<>?');$q->execute([$source,$id]);if($q->fetch())$errors[]='Esiste già un redirect per questo percorso.';
        if($errors){$redirect=['id'=>$id,'source_path'=>$source,'target_path'=>$target,'status_code'=>$code,'enabled'=>$enabled];$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/redirect_form.php';return;}
        if($id){$s=$pdo->prepare('UPDATE seo_redirects SET source_path=?,target_path=?,status_code=?,enabled=? WHERE id=?');$s->execute([$source,$target,$code,$enabled,$id]);$entityId=$id;$action='redirect.update';}
        else{$s=$pdo->prepare('INSERT INTO seo_redirects(source_path,target_path,status_code,enabled) VALUES(?,?,?,?)');$s->execute([$source,$target,$code,$enabled]);$entityId=(int)$pdo->lastInsertId();$action='redirect.create';}
        Audit::log($action,'seo_redirect',$entityId,['source'=>$source,'target'=>$target,'status_code'=>$code]);
        header('Location:/admin/redirects');exit;
    }

    private static function path(string $value): string
    {
        $value=trim($value);
        if($value==='')return '';
        $parts=parse_url($value);
        if(isset($parts['host']))return '';
        $path=(string)($parts['path']??'');
        if($path===''||$path[0]!=='/')$path='/'.ltrim($path,'/');
        if(!empty($parts['query']))$path.='?'.$parts['query'];
        return $path;
    }
}
