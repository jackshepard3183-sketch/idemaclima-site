<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

final class IncentivesController
{
    public static function requests():void
    {
        AdminAuth::requireLogin();self::ensureTable();$status=(string)($_GET['status']??'');$allowed=['new','in_progress','closed','spam'];
        if(in_array($status,$allowed,true)){$s=Database::connection()->prepare('SELECT * FROM incentive_requests WHERE status=? ORDER BY created_at DESC');$s->execute([$status]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);}else{$rows=Database::connection()->query('SELECT * FROM incentive_requests ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);}
        self::view('incentive_requests',['title'=>'Richieste Detrazioni','rows'=>$rows,'status'=>$status]);
    }
    public static function request():void
    {
        AdminAuth::requireLogin();self::ensureTable();$id=Validator::int($_GET['id']??0);$s=Database::connection()->prepare('SELECT * FROM incentive_requests WHERE id=?');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row){http_response_code(404);exit('Richiesta non trovata');}self::view('incentive_request',['title'=>'Richiesta Detrazioni #'.$id,'row'=>$row]);
    }
    public static function update():void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$status=in_array($_POST['status']??'', ['new','in_progress','closed','spam'],true)?(string)$_POST['status']:'new';$notes=trim((string)($_POST['admin_notes']??''));if(mb_strlen($notes)>10000){http_response_code(422);exit('Note troppo lunghe');}
        $pdo=Database::connection();self::ensureTable();$s=$pdo->prepare('SELECT status FROM incentive_requests WHERE id=?');$s->execute([$id]);$before=$s->fetchColumn();if($before===false){http_response_code(404);exit('Richiesta non trovata');}
        $reviewed=$status==='new'?null:date('Y-m-d H:i:s');$pdo->prepare('UPDATE incentive_requests SET status=?,admin_notes=?,reviewed_at=? WHERE id=?')->execute([$status,$notes?:null,$reviewed,$id]);Audit::log('incentive_request.update','incentive_request',$id,['from'=>$before,'to'=>$status]);header('Location:/idemaclima/admin/incentives/request?id='.$id);exit;
    }
    private static function ensureTable():void{Database::connection()->exec("CREATE TABLE IF NOT EXISTS incentive_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,first_name VARCHAR(120) NOT NULL,last_name VARCHAR(120) NOT NULL,company VARCHAR(190) NULL,region VARCHAR(120) NOT NULL,province VARCHAR(8) NOT NULL,city VARCHAR(120) NOT NULL,postal_code VARCHAR(12) NOT NULL,phone VARCHAR(50) NOT NULL,email VARCHAR(190) NOT NULL,professional_role VARCHAR(80) NOT NULL,status ENUM('new','in_progress','closed','spam') NOT NULL DEFAULT 'new',admin_notes TEXT NULL,privacy_accepted_at DATETIME NOT NULL,reviewed_at DATETIME NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_incentive_status(status,created_at),KEY idx_incentive_email(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}
    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/'.$file.'.php';}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
}
