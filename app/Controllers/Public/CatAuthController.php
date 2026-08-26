<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Auth\CatAuth;
use App\Core\Security;

final class CatAuthController
{
    public static function loginForm(): void
    {
        if (CatAuth::user()) { header('Location: /campus/cat'); exit; }
        self::render('campus/cat_login',['title'=>'Accesso CAT','csrf'=>Security::csrfToken(),'error'=>null]);
    }

    public static function login(): void
    {
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        $login=trim((string)($_POST['login']??''));$password=(string)($_POST['password']??'');
        if(CatAuth::attempt($login,$password)){
            $to=(string)($_SESSION['cat_return_to']??'/campus/cat'); unset($_SESSION['cat_return_to']);
            if(!str_starts_with($to,'/campus/cat'))$to='/campus/cat'; header('Location: '.$to); exit;
        }
        self::render('campus/cat_login',['title'=>'Accesso CAT','csrf'=>Security::csrfToken(),'error'=>'Credenziali non valide o account disattivato.']);
    }

    public static function logout(): void
    {
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        CatAuth::logout(); header('Location: /campus/cat/login'); exit;
    }

    private static function render(string $view,array $data):void{extract($data,EXTR_SKIP);header('Content-Type:text/html;charset=UTF-8');require dirname(__DIR__,2).'/Views/public/_layout_start.php';require dirname(__DIR__,2).'/Views/public/'.$view.'.php';require dirname(__DIR__,2).'/Views/public/_layout_end.php';}
}
