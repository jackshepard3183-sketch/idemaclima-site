<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

final class CampusController
{
    public static function events(): void
    {
        AdminAuth::requireLogin();
        $events=Database::connection()->query('SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id) registrations FROM events e ORDER BY e.starts_at DESC')->fetchAll(PDO::FETCH_ASSOC);
        self::view('campus_events',['title'=>'Campus - Eventi','events'=>$events]);
    }

    public static function eventForm(): void
    {
        AdminAuth::requireLogin();$id=Validator::int($_GET['id']??0);$pdo=Database::connection();
        $event=['id'=>0,'title'=>'','slug'=>'','audience'=>'public','location'=>'','address'=>'','starts_at'=>'','ends_at'=>'','short_description'=>'','description'=>'','max_seats'=>'','registration_open'=>1,'registration_deadline'=>'','published'=>0,'cancelled'=>0,'sort_order'=>0];
        if($id){$s=$pdo->prepare('SELECT * FROM events WHERE id=?');$s->execute([$id]);$event=$s->fetch(PDO::FETCH_ASSOC)?:$event;}
        self::view('campus_event_form',['title'=>'Evento Campus','event'=>$event,'errors'=>[]]);
    }

    public static function saveEvent(): void
    {
        AdminAuth::requireLogin();self::csrf();$errors=[];$id=Validator::int($_POST['id']??0);
        $title=Validator::requiredString($_POST['title']??'','Titolo',220,$errors);$slug=trim((string)($_POST['slug']??''));if($slug==='')$slug=self::slugify($title);
        $audience=in_array($_POST['audience']??'', ['public','cat'],true)?$_POST['audience']:'public';$starts=trim((string)($_POST['starts_at']??''));if($starts==='')$errors[]='Data inizio obbligatoria.';
        $data=['id'=>$id,'title'=>$title,'slug'=>$slug,'audience'=>$audience,'location'=>trim((string)($_POST['location']??'')),'address'=>trim((string)($_POST['address']??'')),'starts_at'=>$starts,'ends_at'=>trim((string)($_POST['ends_at']??'')),'short_description'=>trim((string)($_POST['short_description']??'')),'description'=>trim((string)($_POST['description']??'')),'max_seats'=>Validator::int($_POST['max_seats']??0)?:null,'registration_open'=>Validator::bool($_POST['registration_open']??0),'registration_deadline'=>trim((string)($_POST['registration_deadline']??''))?:null,'published'=>Validator::bool($_POST['published']??0),'cancelled'=>Validator::bool($_POST['cancelled']??0),'sort_order'=>Validator::int($_POST['sort_order']??0)];
        if($errors){self::view('campus_event_form',['title'=>'Evento Campus','event'=>$data,'errors'=>$errors]);return;}
        $pdo=Database::connection();
        if($id){$s=$pdo->prepare('UPDATE events SET title=?,slug=?,audience=?,location=?,address=?,starts_at=?,ends_at=?,short_description=?,description=?,max_seats=?,registration_open=?,registration_deadline=?,published=?,cancelled=?,sort_order=? WHERE id=?');$s->execute([$title,$slug,$audience,$data['location']?:null,$data['address']?:null,$starts,$data['ends_at']?:null,$data['short_description']?:null,$data['description']?:null,$data['max_seats'],$data['registration_open'],$data['registration_deadline'],$data['published'],$data['cancelled'],$data['sort_order'],$id]);}
        else{$s=$pdo->prepare('INSERT INTO events(title,slug,audience,location,address,starts_at,ends_at,short_description,description,max_seats,registration_open,registration_deadline,published,cancelled,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$s->execute([$title,$slug,$audience,$data['location']?:null,$data['address']?:null,$starts,$data['ends_at']?:null,$data['short_description']?:null,$data['description']?:null,$data['max_seats'],$data['registration_open'],$data['registration_deadline'],$data['published'],$data['cancelled'],$data['sort_order']]);}
        header('Location:/admin/campus/events');exit;
    }

    public static function registrations(): void
    {
        AdminAuth::requireLogin();
        $rows=Database::connection()->query('SELECT r.*,e.title event_title,e.audience,c.company_name cat_company FROM event_registrations r JOIN events e ON e.id=r.event_id LEFT JOIN cat_accounts c ON c.id=r.cat_account_id ORDER BY r.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
        self::view('campus_registrations',['title'=>'Campus - Iscrizioni','rows'=>$rows]);
    }

    public static function updateRegistration(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$status=in_array($_POST['status']??'', ['registered','confirmed','cancelled','waitlist'],true)?$_POST['status']:'registered';$attended=($_POST['attended']??'')===''?null:Validator::bool($_POST['attended']);Database::connection()->prepare('UPDATE event_registrations SET status=?,attended=? WHERE id=?')->execute([$status,$attended,$id]);header('Location:/admin/campus/registrations');exit;
    }

    public static function catUsers(): void
    {
        AdminAuth::requireLogin();$users=Database::connection()->query('SELECT * FROM cat_accounts ORDER BY company_name,contact_last_name')->fetchAll(PDO::FETCH_ASSOC);self::view('cat_users',['title'=>'Utenti CAT','users'=>$users]);
    }

    public static function catUserForm(): void
    {
        AdminAuth::requireLogin();$id=Validator::int($_GET['id']??0);$user=['id'=>0,'company_name'=>'','contact_first_name'=>'','contact_last_name'=>'','email'=>'','phone'=>'','username'=>'','active'=>1,'verified_at'=>''];if($id){$s=Database::connection()->prepare('SELECT id,company_name,contact_first_name,contact_last_name,email,phone,username,active,verified_at FROM cat_accounts WHERE id=?');$s->execute([$id]);$user=$s->fetch(PDO::FETCH_ASSOC)?:$user;}self::view('cat_user_form',['title'=>'Utente CAT','cat'=>$user,'errors'=>[]]);
    }

    public static function saveCatUser(): void
    {
        AdminAuth::requireLogin();self::csrf();$errors=[];$id=Validator::int($_POST['id']??0);$company=Validator::requiredString($_POST['company_name']??'','Azienda',190,$errors);$first=Validator::requiredString($_POST['contact_first_name']??'','Nome',120,$errors);$last=Validator::requiredString($_POST['contact_last_name']??'','Cognome',120,$errors);$email=trim((string)($_POST['email']??''));$username=trim((string)($_POST['username']??''));if(!filter_var($email,FILTER_VALIDATE_EMAIL))$errors[]='Email non valida.';if($username==='')$errors[]='Username obbligatorio.';$password=(string)($_POST['password']??'');if(!$id && strlen($password)<10)$errors[]='Password iniziale: almeno 10 caratteri.';$active=Validator::bool($_POST['active']??0);
        $cat=['id'=>$id,'company_name'=>$company,'contact_first_name'=>$first,'contact_last_name'=>$last,'email'=>$email,'phone'=>trim((string)($_POST['phone']??'')),'username'=>$username,'active'=>$active,'verified_at'=>''];if($errors){self::view('cat_user_form',['title'=>'Utente CAT','cat'=>$cat,'errors'=>$errors]);return;}
        $pdo=Database::connection();try{if($id){if($password!==''){$s=$pdo->prepare('UPDATE cat_accounts SET company_name=?,contact_first_name=?,contact_last_name=?,email=?,phone=?,username=?,password_hash=?,active=? WHERE id=?');$s->execute([$company,$first,$last,$email,$cat['phone']?:null,$username,password_hash($password,PASSWORD_DEFAULT),$active,$id]);}else{$s=$pdo->prepare('UPDATE cat_accounts SET company_name=?,contact_first_name=?,contact_last_name=?,email=?,phone=?,username=?,active=? WHERE id=?');$s->execute([$company,$first,$last,$email,$cat['phone']?:null,$username,$active,$id]);}}else{$s=$pdo->prepare('INSERT INTO cat_accounts(company_name,contact_first_name,contact_last_name,email,phone,username,password_hash,active,verified_at) VALUES(?,?,?,?,?,?,?,?,NOW())');$s->execute([$company,$first,$last,$email,$cat['phone']?:null,$username,password_hash($password,PASSWORD_DEFAULT),$active]);}}catch(\PDOException $e){if((string)$e->getCode()==='23000'){$errors[]='Email o username già utilizzato.';self::view('cat_user_form',['title'=>'Utente CAT','cat'=>$cat,'errors'=>$errors]);return;}throw $e;}header('Location:/admin/cat/users');exit;
    }

    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/'.$file.'.php';}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
    private static function slugify(string $v):string{$a=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',strtolower(trim($v)))?:$v;return trim((string)preg_replace('/[^a-z0-9]+/','-',$a),'-');}
}
