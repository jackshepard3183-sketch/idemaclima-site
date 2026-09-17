<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Auth\CatAuth;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Database;
use App\Core\Validator;
use App\Services\CampusMailService;

final class CatAuthController
{
    public static function loginForm(): void
    {
        if (CatAuth::user()) { header('Location: /idemaclima/campus/cat'); exit; }
        self::render('campus/cat_login',['title'=>'Accesso CAT','csrf'=>Security::csrfToken(),'error'=>null]);
    }

    public static function login(): void
    {
        if (!RateLimiter::allow('cat-login', 10, 900)) RateLimiter::reject(900);
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        $login=trim((string)($_POST['login']??''));$password=(string)($_POST['password']??'');
        if(CatAuth::attempt($login,$password)){
            $to=(string)($_SESSION['cat_return_to']??'/idemaclima/campus/cat'); unset($_SESSION['cat_return_to']);
            if(!str_starts_with($to,'/idemaclima/campus/cat'))$to='/idemaclima/campus/cat'; header('Location: '.$to); exit;
        }
        self::render('campus/cat_login',['title'=>'Accesso CAT','csrf'=>Security::csrfToken(),'error'=>'Credenziali non valide o account disattivato.']);
    }

    public static function registerForm(): void
    {
        self::render('campus/cat_register',['title'=>'Registrazione CAT','csrf'=>Security::csrfToken(),'error'=>null,'success'=>false,'old'=>[]]);
    }

    public static function register(): void
    {
        if(!RateLimiter::allow('cat-register',4,3600))RateLimiter::reject(3600);
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        if(trim((string)($_POST['company_website']??''))!==''){http_response_code(422);exit('Richiesta non valida');}
        $old=['first_name'=>Validator::naturalText($_POST['first_name']??''),'last_name'=>Validator::naturalText($_POST['last_name']??''),'company_name'=>Validator::naturalText($_POST['company_name']??''),'email'=>strtolower(trim((string)($_POST['email']??''))),'phone'=>trim((string)($_POST['phone']??''))];
        $password=(string)($_POST['password']??'');$privacy=isset($_POST['privacy']);$error=null;
        foreach(['first_name','last_name','company_name','phone'] as $field)if($old[$field]===''||mb_strlen($old[$field])>190)$error='Compila correttamente tutti i campi obbligatori.';
        if(!filter_var($old['email'],FILTER_VALIDATE_EMAIL)||mb_strlen($old['email'])>190)$error='Inserisci un indirizzo email valido.';
        if(strlen($password)<12||!preg_match('/[A-Z]/',$password)||!preg_match('/[a-z]/',$password)||!preg_match('/\d/',$password)||!preg_match('/[^A-Za-z0-9]/',$password))$error='La password deve avere almeno 12 caratteri, maiuscola, minuscola, numero e simbolo.';
        if(!$privacy)$error='È necessario leggere e accettare l’informativa privacy.';
        if($error!==null){self::render('campus/cat_register',['title'=>'Registrazione CAT','csrf'=>Security::csrfToken(),'error'=>$error,'success'=>false,'old'=>$old]);return;}
        try{
            $pdo=Database::connection();$s=$pdo->prepare('INSERT INTO cat_accounts(company_name,contact_first_name,contact_last_name,email,phone,username,password_hash,password_changed_at,active,verified_at,disabled_at) VALUES(?,?,?,?,?,?,?,NOW(),0,NULL,NULL)');
            $s->execute([$old['company_name'],$old['first_name'],$old['last_name'],$old['email'],$old['phone'],$old['email'],password_hash($password,PASSWORD_DEFAULT)]);$requestId=(int)$pdo->lastInsertId();
        }catch(\PDOException $e){
            if((string)$e->getCode()==='23000')$error='Esiste già una richiesta o un account associato a questa email.';else throw $e;
            self::render('campus/cat_register',['title'=>'Registrazione CAT','csrf'=>Security::csrfToken(),'error'=>$error,'success'=>false,'old'=>$old]);return;
        }
        CampusMailService::notifyInternal('Nuovo CAT da approvare #'.$requestId,['Richiesta'=>'#'.$requestId,'Azienda / CAT'=>$old['company_name'],'Referente'=>$old['first_name'].' '.$old['last_name'],'Email'=>$old['email'],'Telefono'=>$old['phone'],'Pannello'=>'https://www.rappresentanzeguanzirolisas.it/idemaclima/admin/cat/users?status=pending'],$old['email']);
        self::render('campus/cat_register',['title'=>'Registrazione CAT','csrf'=>Security::csrfToken(),'error'=>null,'success'=>true,'old'=>[]]);
    }

    public static function logout(): void
    {
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        CatAuth::logout(); header('Location: /idemaclima/campus/cat/login'); exit;
    }

    private static function render(string $view,array $data):void{extract($data,EXTR_SKIP);header('Content-Type:text/html;charset=UTF-8');require dirname(__DIR__,2).'/Views/public/_layout_start.php';require dirname(__DIR__,2).'/Views/public/'.$view.'.php';require dirname(__DIR__,2).'/Views/public/_layout_end.php';}
}
