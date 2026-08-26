<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\PrivateUpload;
use App\Core\Security;
use Throwable;

final class ContactController
{
    public static function form(): void
    {
        self::render('contact/form', ['title'=>'Contatti','csrf'=>Security::csrfToken(),'errors'=>[],'old'=>[]]);
    }

    public static function submit(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            self::render('contact/result',['title'=>'Sessione scaduta','success'=>false,'message'=>'Sessione non valida. Ricarica il modulo e riprova.']);
            return;
        }
        $old=array_map(static fn($v)=>is_string($v)?trim($v):$v,$_POST);$errors=[];
        foreach(['first_name','last_name','region','province','city','email','email_confirm','subject','message'] as $f) if(trim((string)($old[$f]??''))==='')$errors[]='Compila tutti i campi obbligatori.';
        if(!filter_var((string)($old['email']??''),FILTER_VALIDATE_EMAIL))$errors[]='Email non valida.';
        if(strcasecmp((string)($old['email']??''),(string)($old['email_confirm']??''))!==0)$errors[]='Le due email non coincidono.';
        if(!isset($old['privacy']))$errors[]='È necessario accettare l’informativa privacy.';
        if($errors){self::render('contact/form',['title'=>'Contatti','csrf'=>Security::csrfToken(),'errors'=>array_values(array_unique($errors)),'old'=>$old]);return;}

        $attachment=PrivateUpload::contactAttachment('attachment',$errors);
        if($errors){if($attachment)PrivateUpload::remove($attachment['path']);self::render('contact/form',['title'=>'Contatti','csrf'=>Security::csrfToken(),'errors'=>array_values(array_unique($errors)),'old'=>$old]);return;}

        try {
            $s=Database::connection()->prepare('INSERT INTO contact_submissions(first_name,last_name,region,province,city,email,phone,subject,message,attachment_path,attachment_name,attachment_mime,privacy_accepted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
            $s->execute([$old['first_name'],$old['last_name'],$old['region'],strtoupper((string)$old['province']),strtoupper((string)$old['city']),$old['email'],$old['phone']?:null,$old['subject'],$old['message'],$attachment['path']??null,$attachment['original_name']??null,$attachment['mime']??null]);
            self::render('contact/result',['title'=>'Messaggio ricevuto','success'=>true,'message'=>'La richiesta è stata inviata correttamente.']);
        } catch(Throwable $e) {
            if($attachment)PrivateUpload::remove($attachment['path']);
            http_response_code(500);self::render('contact/result',['title'=>'Errore','success'=>false,'message'=>'Non è stato possibile inviare la richiesta.']);
        }
    }

    private static function render(string $view,array $data):void{extract($data,EXTR_SKIP);header('Content-Type:text/html; charset=UTF-8');require dirname(__DIR__,2).'/Views/public/_layout_start.php';require dirname(__DIR__,2).'/Views/public/'.$view.'.php';require dirname(__DIR__,2).'/Views/public/_layout_end.php';}
}
