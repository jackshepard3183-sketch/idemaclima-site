<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Auth\CatAuth;
use App\Core\Database;
use App\Core\Security;
use PDO;

final class CampusController
{
    public static function index(): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->query('SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status IN ("registered","confirmed")) AS booked FROM events e WHERE e.audience="public" AND e.published=1 AND e.cancelled=0 ORDER BY e.starts_at ASC');
        self::render('campus/index',['title'=>'Campus','events'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'csrf'=>Security::csrfToken()]);
    }

    public static function event(string $slug): void
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status IN ("registered","confirmed")) AS booked FROM events e WHERE e.slug=? AND e.audience="public" AND e.published=1 LIMIT 1');
        $stmt->execute([$slug]);
        $event=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$event){self::notFound();return;}
        self::render('campus/event',['title'=>$event['title'],'event'=>$event,'csrf'=>Security::csrfToken(),'catUser'=>null]);
    }

    public static function catIndex(): void
    {
        $catUser=CatAuth::requireLogin();
        $stmt=Database::connection()->query('SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status IN ("registered","confirmed")) AS booked FROM events e WHERE e.audience="cat" AND e.published=1 AND e.cancelled=0 ORDER BY e.starts_at ASC');
        self::render('campus/cat_index',['title'=>'Campus CAT','events'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'catUser'=>$catUser,'csrf'=>Security::csrfToken()]);
    }

    public static function catEvent(string $slug): void
    {
        $catUser=CatAuth::requireLogin();
        $stmt=Database::connection()->prepare('SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status IN ("registered","confirmed")) AS booked FROM events e WHERE e.slug=? AND e.audience="cat" AND e.published=1 LIMIT 1');
        $stmt->execute([$slug]);
        $event=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$event){self::notFound();return;}
        self::render('campus/event',['title'=>$event['title'],'event'=>$event,'csrf'=>Security::csrfToken(),'catUser'=>$catUser]);
    }

    public static function register(string $slug): void
    {
        if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT * FROM events WHERE slug=? AND published=1 AND cancelled=0 LIMIT 1');
        $stmt->execute([$slug]);$event=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$event){self::notFound();return;}
        $catUser=null;
        if($event['audience']==='cat') $catUser=CatAuth::requireLogin();
        if(!(int)$event['registration_open']){self::render('campus/result',['title'=>'Iscrizioni chiuse','message'=>'Le iscrizioni a questo evento sono chiuse.']);return;}
        if(!empty($event['registration_deadline']) && strtotime((string)$event['registration_deadline']) < time()){self::render('campus/result',['title'=>'Iscrizioni chiuse','message'=>'Il termine per l’iscrizione è scaduto.']);return;}
        $first=trim((string)($_POST['first_name']??($catUser['contact_first_name']??'')));
        $last=trim((string)($_POST['last_name']??($catUser['contact_last_name']??'')));
        $email=trim((string)($_POST['email']??($catUser['email']??'')));
        if($first===''||$last===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||empty($_POST['privacy'])){self::render('campus/result',['title'=>'Dati non validi','message'=>'Compila correttamente i campi obbligatori e accetta la privacy.']);return;}
        $count=$pdo->prepare('SELECT COUNT(*) FROM event_registrations WHERE event_id=? AND status IN ("registered","confirmed")');$count->execute([(int)$event['id']]);
        $booked=(int)$count->fetchColumn();
        $status=(!empty($event['max_seats']) && $booked >= (int)$event['max_seats'])?'waitlist':'registered';
        try{
            $ins=$pdo->prepare('INSERT INTO event_registrations(event_id,cat_account_id,first_name,last_name,email,phone,company,role,notes,status,privacy_accepted_at) VALUES(?,?,?,?,?,?,?,?,?,?,NOW())');
            $ins->execute([(int)$event['id'],$catUser['id']??null,$first,$last,$email,trim((string)($_POST['phone']??($catUser['phone']??'')))?:null,trim((string)($_POST['company']??($catUser['company_name']??'')))?:null,trim((string)($_POST['role']??''))?:null,trim((string)($_POST['notes']??''))?:null,$status]);
            self::render('campus/result',['title'=>'Iscrizione ricevuta','message'=>$status==='waitlist'?'Posti esauriti: sei stato inserito in lista d’attesa.':'Iscrizione registrata correttamente.']);
        }catch(\PDOException $e){
            if((string)$e->getCode()==='23000'){self::render('campus/result',['title'=>'Iscrizione già presente','message'=>'Risulta già un’iscrizione per questa email.']);return;} throw $e;
        }
    }

    private static function render(string $view,array $data):void{extract($data,EXTR_SKIP);header('Content-Type:text/html;charset=UTF-8');require dirname(__DIR__,2).'/Views/public/_layout_start.php';require dirname(__DIR__,2).'/Views/public/'.$view.'.php';require dirname(__DIR__,2).'/Views/public/_layout_end.php';}
    private static function notFound():void{http_response_code(404);self::render('404',['title'=>'Pagina non trovata']);}
}
