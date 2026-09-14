<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Validator;
use App\Services\IncentivesMailService;
use Throwable;

final class IncentivesController
{
    public static function submit(): void
    {
        if(!RateLimiter::allow('incentives-submit',5,600))RateLimiter::reject(600);
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);EditorialController::page('detrazioni-e-incentivi',['errors'=>['Sessione scaduta. Ricarica la pagina e riprova.'],'old'=>$_POST]);return;}
        $old=array_map(static fn($v)=>is_string($v)?trim($v):$v,$_POST);$errors=[];
        if(trim((string)($old['company_website']??''))!==''){header('Location:/idemaclima/detrazioni-e-incentivi/consulenza-energetica?sent=1');exit;}
        foreach(['first_name','last_name','region','province','city','postal_code','phone','email','email_confirm','role'] as $field)if(trim((string)($old[$field]??''))==='')$errors[]='Compila tutti i campi obbligatori.';
        $limits=['first_name'=>120,'last_name'=>120,'company'=>190,'region'=>120,'province'=>120,'city'=>120,'postal_code'=>12,'phone'=>50,'email'=>190,'role'=>80];
        foreach($limits as $field=>$max)if(mb_strlen((string)($old[$field]??''))>$max)$errors[]='Uno dei valori inseriti è troppo lungo.';
        if(!filter_var((string)($old['email']??''),FILTER_VALIDATE_EMAIL))$errors[]='Email non valida.';
        if(strcasecmp((string)($old['email']??''),(string)($old['email_confirm']??''))!==0)$errors[]='Le due email non coincidono.';
        if(!isset($old['privacy']))$errors[]='È necessario accettare l’informativa privacy.';
        if($errors){EditorialController::page('detrazioni-e-incentivi',['errors'=>array_values(array_unique($errors)),'old'=>$old]);return;}
        try{
            $pdo=Database::connection();self::ensureTable();
            $stmt=$pdo->prepare('INSERT INTO incentive_requests(first_name,last_name,company,region,province,city,postal_code,phone,email,professional_role,privacy_accepted_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())');
            $company=Validator::naturalText($old['company']??'');
            $stmt->execute([Validator::naturalText($old['first_name']),Validator::naturalText($old['last_name']),$company!==''?$company:null,Validator::naturalText($old['region']),Validator::provinceCode($old['province']),Validator::naturalText($old['city']),strtoupper((string)$old['postal_code']),$old['phone'],strtolower((string)$old['email']),Validator::naturalText($old['role'])]);
            IncentivesMailService::notify($old);
            header('Location:/idemaclima/detrazioni-e-incentivi/consulenza-energetica?sent=1');exit;
        }catch(Throwable){http_response_code(500);EditorialController::page('detrazioni-e-incentivi',['errors'=>['Non è stato possibile inviare la richiesta. Riprova più tardi.'],'old'=>$old]);}
    }

    private static function ensureTable():void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS incentive_requests (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,first_name VARCHAR(120) NOT NULL,last_name VARCHAR(120) NOT NULL,company VARCHAR(190) NULL,region VARCHAR(120) NOT NULL,province VARCHAR(120) NOT NULL,city VARCHAR(120) NOT NULL,postal_code VARCHAR(12) NOT NULL,phone VARCHAR(50) NOT NULL,email VARCHAR(190) NOT NULL,professional_role VARCHAR(80) NOT NULL,status ENUM('new','in_progress','closed','spam') NOT NULL DEFAULT 'new',admin_notes TEXT NULL,privacy_accepted_at DATETIME NOT NULL,reviewed_at DATETIME NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,KEY idx_incentive_status(status,created_at),KEY idx_incentive_email(email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}
