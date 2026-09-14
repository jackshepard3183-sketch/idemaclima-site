<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\PrivateUpload;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Validator;
use App\Services\ContactMailService;
use Throwable;

final class ContactController
{
    public static function form(): void
    {
        self::ensurePostalCodeColumn();
        self::render('contact/form', ['title'=>'Contatti','csrf'=>Security::csrfToken(),'errors'=>[],'old'=>[]]);
    }

    public static function submit(): void
    {
        if (!RateLimiter::allow('contact-submit', 5, 600)) RateLimiter::reject(600);
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            self::render('contact/result',['title'=>'Sessione scaduta','success'=>false,'message'=>'Sessione non valida. Ricarica il modulo e riprova.']);
            return;
        }

        $old=array_map(static fn($v)=>is_string($v)?trim($v):$v,$_POST);$errors=[];
        if (trim((string)($old['company_website'] ?? '')) !== '') {
            self::render('contact/result',['title'=>'Messaggio ricevuto','success'=>true,'message'=>'La richiesta è stata inviata correttamente.']);
            return;
        }

        foreach(['first_name','last_name','region','province','city','postal_code','phone','email','email_confirm','subject','message'] as $f) {
            if(trim((string)($old[$f]??''))==='')$errors[]='Compila tutti i campi obbligatori.';
        }

        self::maxLen($old,'first_name',120,'Nome',$errors);
        self::maxLen($old,'last_name',120,'Cognome',$errors);
        self::maxLen($old,'region',120,'Regione',$errors);
        self::maxLen($old,'province',120,'Provincia',$errors);
        self::maxLen($old,'city',120,'Città',$errors);
        self::maxLen($old,'postal_code',12,'CAP',$errors);
        self::maxLen($old,'email',190,'Email',$errors);
        self::maxLen($old,'phone',50,'Telefono',$errors);
        self::maxLen($old,'subject',220,'Oggetto',$errors);
        self::maxLen($old,'message',10000,'Messaggio',$errors);

        if(!filter_var((string)($old['email']??''),FILTER_VALIDATE_EMAIL))$errors[]='Email non valida.';
        if(strcasecmp((string)($old['email']??''),(string)($old['email_confirm']??''))!==0)$errors[]='Le due email non coincidono.';
        if(!isset($old['privacy']))$errors[]='È necessario accettare l’informativa privacy.';

        if($errors){self::render('contact/form',['title'=>'Contatti','csrf'=>Security::csrfToken(),'errors'=>array_values(array_unique($errors)),'old'=>$old]);return;}

        $attachment=PrivateUpload::contactAttachment('attachment',$errors);
        if($errors){if($attachment)PrivateUpload::remove($attachment['path']);self::render('contact/form',['title'=>'Contatti','csrf'=>Security::csrfToken(),'errors'=>array_values(array_unique($errors)),'old'=>$old]);return;}

        try {
            self::ensurePostalCodeColumn();
            $pdo=Database::connection();
            $s=$pdo->prepare('INSERT INTO contact_submissions(first_name,last_name,region,province,city,postal_code,email,phone,subject,message,attachment_path,attachment_name,attachment_mime,privacy_accepted_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
            $s->execute([
                Validator::naturalText($old['first_name']),Validator::naturalText($old['last_name']),Validator::naturalText($old['region']),Validator::provinceCode($old['province']),Validator::naturalText($old['city']),strtoupper((string)$old['postal_code']),
                strtolower((string)$old['email']),(string)$old['phone'],Validator::naturalText($old['subject']),trim((string)$old['message']),
                $attachment['path']??null,$attachment['original_name']??null,$attachment['mime']??null
            ]);
            ContactMailService::notify($old, $attachment['original_name']??null);
            self::render('contact/result',['title'=>'Messaggio ricevuto','success'=>true,'message'=>'La richiesta è stata inviata correttamente.']);
        } catch(Throwable $e) {
            if($attachment)PrivateUpload::remove($attachment['path']);
            http_response_code(500);self::render('contact/result',['title'=>'Errore','success'=>false,'message'=>'Non è stato possibile inviare la richiesta.']);
        }
    }

    private static function ensurePostalCodeColumn(): void
    {
        $pdo=Database::connection();
        $check=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='contact_submissions' AND COLUMN_NAME='postal_code'");
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE contact_submissions ADD COLUMN postal_code VARCHAR(12) NULL AFTER city");
        }
    }

    private static function maxLen(array $old,string $field,int $max,string $label,array &$errors):void
    {
        if(mb_strlen((string)($old[$field]??''))>$max)$errors[]=$label.' troppo lungo.';
    }

    private static function render(string $view,array $data):void{extract($data,EXTR_SKIP);header('Content-Type:text/html; charset=UTF-8');require dirname(__DIR__,2).'/Views/public/_layout_start.php';require dirname(__DIR__,2).'/Views/public/'.$view.'.php';require dirname(__DIR__,2).'/Views/public/_layout_end.php';}
}
