<?php


declare(strict_types=1);


namespace App\Controllers\Admin;


use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Upload;
use App\Core\Validator;
use PDO;


final class CampusController
{
    public static function events(): void
    {
        AdminAuth::requireLogin();
        $audience=in_array($_GET['audience']??'', ['public','cat'],true)?(string)$_GET['audience']:'';
        $sql='SELECT e.*, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id) registrations FROM events e'.($audience!==''?' WHERE e.audience=?':'').' ORDER BY e.starts_at DESC';
        $stmt=Database::connection()->prepare($sql);$stmt->execute($audience!==''?[$audience]:[]);
        self::view('campus_events',['title'=>'Campus - Eventi','events'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'audience'=>$audience]);
    }


    public static function eventForm(): void
    {
        AdminAuth::requireLogin();
        $id=Validator::int($_GET['id']??0);
        $pdo=Database::connection();
        $event=['id'=>0,'title'=>'','slug'=>'','audience'=>'public','category'=>'','location'=>'','address'=>'','starts_at'=>'','ends_at'=>'','short_description'=>'','speaker'=>'','description'=>'','program'=>'','cover_image'=>'','max_seats'=>'','waitlist_enabled'=>1,'registration_open'=>1,'registration_deadline'=>'','fee_amount'=>'','fee_note'=>'','published'=>0,'cancelled'=>0,'sort_order'=>0];
        if($id){
            $s=$pdo->prepare('SELECT * FROM events WHERE id=?');
            $s->execute([$id]);
            $row=$s->fetch(PDO::FETCH_ASSOC);
            if(!$row){http_response_code(404);exit('Evento non trovato');}
            $event=$row;
        }
        self::view('campus_event_form',['title'=>'Evento Campus','event'=>$event,'errors'=>[]]);
    }


    public static function saveEvent(): void
    {
        AdminAuth::requireLogin();
        self::csrf();
        $errors=[];
        $id=Validator::int($_POST['id']??0);
        $title=Validator::requiredString($_POST['title']??'','Titolo',220,$errors);
        $slug=self::slugify((string)($_POST['slug']??''));
        if($slug==='')$slug=self::slugify($title);
        if($slug==='')$errors[]='Slug non valido.';
        $audience=in_array($_POST['audience']??'', ['public','cat'],true)?$_POST['audience']:'public';
        $starts=self::mysqlDateTime($_POST['starts_at']??null);
        if($starts===null)$errors[]='Data inizio obbligatoria o non valida.';
        $ends=self::mysqlDateTime($_POST['ends_at']??null);
        $deadline=self::mysqlDateTime($_POST['registration_deadline']??null);
        if($starts && $ends && $ends<$starts)$errors[]='La data di fine non può precedere l’inizio.';
        if($starts && $deadline && $deadline>$starts)$errors[]='La scadenza iscrizioni non può essere successiva all’inizio dell’evento.';
        $maxSeats=Validator::int($_POST['max_seats']??0)?:null;
        if($maxSeats!==null && $maxSeats<1)$errors[]='Numero massimo posti non valido.';
        $location=Validator::optionalString($_POST['location']??'',190,'Luogo',$errors);
        $address=Validator::optionalString($_POST['address']??'',255,'Indirizzo',$errors);
        $short=Validator::optionalString($_POST['short_description']??'',1000,'Descrizione breve',$errors);
        $categoryChoice=trim((string)($_POST['category_choice']??''));
        $standardCategories=['Residenziale','Commerciale','Sistemi industriali','Pompe di calore'];
        if($categoryChoice==='Altro'){
            $category=Validator::optionalString($_POST['category_custom']??'',120,'Categoria personalizzata',$errors);
            if($category==='')$errors[]='Inserisci la categoria personalizzata.';
        } elseif(in_array($categoryChoice,$standardCategories,true)){
            $category=$categoryChoice;
        } else {
            $category='';
            $errors[]='Seleziona una categoria valida.';
        }
        $speaker=Validator::optionalString($_POST['speaker']??'',190,'Relatore',$errors);
        $feeRaw=str_replace(',','.',trim((string)($_POST['fee_amount']??'')));
        $feeAmount=$feeRaw===''?null:(is_numeric($feeRaw)?round((float)$feeRaw,2):null);
        if($feeRaw!=='' && ($feeAmount===null || $feeAmount<0 || $feeAmount>99999999.99))$errors[]='Quota non valida.';
        $feeNote=Validator::optionalString($_POST['fee_note']??'',255,'Nota quota',$errors);
        $description=trim((string)($_POST['description']??''));
        $program=trim((string)($_POST['program']??''));
        if(mb_strlen($description)>50000)$errors[]='Descrizione troppo lunga.';
        if(mb_strlen($program)>30000)$errors[]='Programma troppo lungo.';
        $pdo=Database::connection();
        $existingImage='';
        if($id){
            $q=$pdo->prepare('SELECT cover_image FROM events WHERE id=?');
            $q->execute([$id]);
            $found=$q->fetchColumn();
            if($found===false){http_response_code(404);exit('Evento non trovato');}
            $existingImage=(string)$found;
        }
        $uploaded=Upload::contentImage('cover_image_file','campus',$errors);
        $cover=$uploaded['path']??$existingImage;
        if($cover==='')$cover=self::defaultCover($audience);
        $data=['id'=>$id,'title'=>$title,'slug'=>$slug,'audience'=>$audience,'category'=>$category,'location'=>$location,'address'=>$address,'starts_at'=>$starts??'','ends_at'=>$ends??'','short_description'=>$short,'speaker'=>$speaker,'description'=>$description,'program'=>$program,'cover_image'=>$cover,'max_seats'=>$maxSeats,'waitlist_enabled'=>Validator::bool($_POST['waitlist_enabled']??0),'registration_open'=>Validator::bool($_POST['registration_open']??0),'registration_deadline'=>$deadline,'fee_amount'=>$feeAmount,'fee_note'=>$feeNote,'published'=>Validator::bool($_POST['published']??0),'cancelled'=>Validator::bool($_POST['cancelled']??0),'sort_order'=>Validator::int($_POST['sort_order']??0)];
        if($errors){
            if($uploaded)Upload::removeManaged($uploaded['path']);
            self::view('campus_event_form',['title'=>'Evento Campus','event'=>$data,'errors'=>$errors]);
            return;
        }
        try {
            $q=$pdo->prepare('SELECT id FROM events WHERE slug=? AND id<>?');
            $q->execute([$slug,$id]);
            if($q->fetchColumn()!==false)throw new \RuntimeException('Slug già utilizzato da un altro evento.');
            if($id){
                $s=$pdo->prepare('UPDATE events SET title=?,slug=?,audience=?,category=?,location=?,address=?,starts_at=?,ends_at=?,short_description=?,speaker=?,description=?,program=?,cover_image=?,max_seats=?,waitlist_enabled=?,registration_open=?,registration_deadline=?,fee_amount=?,fee_note=?,published=?,cancelled=?,sort_order=? WHERE id=?');
                $s->execute([$title,$slug,$audience,$category?:null,$location?:null,$address?:null,$starts,$ends,$short?:null,$speaker?:null,$description?:null,$program?:null,$cover?:null,$maxSeats,$data['waitlist_enabled'],$data['registration_open'],$deadline,$feeAmount,$feeNote?:null,$data['published'],$data['cancelled'],$data['sort_order'],$id]);
                if($uploaded && $existingImage && $existingImage!==$cover)Upload::removeManaged($existingImage);
                $entityId=$id;$action='campus.event.update';
            } else {
                $s=$pdo->prepare('INSERT INTO events(title,slug,audience,category,location,address,starts_at,ends_at,short_description,speaker,description,program,cover_image,max_seats,waitlist_enabled,registration_open,registration_deadline,fee_amount,fee_note,published,cancelled,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $s->execute([$title,$slug,$audience,$category?:null,$location?:null,$address?:null,$starts,$ends,$short?:null,$speaker?:null,$description?:null,$program?:null,$cover?:null,$maxSeats,$data['waitlist_enabled'],$data['registration_open'],$deadline,$feeAmount,$feeNote?:null,$data['published'],$data['cancelled'],$data['sort_order']]);
                $entityId=(int)$pdo->lastInsertId();$action='campus.event.create';
            }
            Audit::log($action,'event',$entityId,['title'=>$title,'audience'=>$audience,'slug'=>$slug]);
        } catch (\Throwable $e) {
            if($uploaded)Upload::removeManaged($uploaded['path']);
            $errors[]=$e instanceof \RuntimeException?$e->getMessage():'Non è stato possibile salvare l’evento.';
            self::view('campus_event_form',['title'=>'Evento Campus','event'=>$data,'errors'=>$errors]);
            return;
        }
        header('Location:/idemaclima/admin/campus/events');exit;
    }


    public static function registrations(): void
    {
        AdminAuth::requireLogin();
        $eventId=Validator::int($_GET['event_id']??0);
        $catAccountId=Validator::int($_GET['cat_account_id']??0);
        $status=in_array($_GET['status']??'', ['registered','confirmed','waitlist','cancelled'],true)?(string)$_GET['status']:'';
        $where=[];$params=[];
        if($eventId){$where[]='r.event_id=?';$params[]=$eventId;}
        if($catAccountId){$where[]='r.cat_account_id=?';$params[]=$catAccountId;}
        if($status!==''){$where[]='r.status=?';$params[]=$status;}
        $pdo=Database::connection();
        self::ensureRegistrationConfirmationColumn($pdo);
        $sql='SELECT r.*,e.title event_title,e.audience,e.starts_at event_starts_at,e.location event_location,c.company_name cat_company FROM event_registrations r JOIN events e ON e.id=r.event_id LEFT JOIN cat_accounts c ON c.id=r.cat_account_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY r.created_at DESC';
        $stmt=$pdo->prepare($sql);$stmt->execute($params);
        $events=$pdo->query('SELECT id,title,starts_at FROM events ORDER BY starts_at DESC')->fetchAll(PDO::FETCH_ASSOC);
        self::view('campus_registrations',['title'=>'Campus - Iscrizioni','rows'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'events'=>$events,'filters'=>['event_id'=>$eventId,'cat_account_id'=>$catAccountId,'status'=>$status]]);
    }


    public static function updateRegistration(): void
    {
        AdminAuth::requireLogin();self::csrf();
        $id=Validator::int($_POST['id']??0);
        $status=in_array($_POST['status']??'', ['registered','confirmed','cancelled','waitlist'],true)?$_POST['status']:'registered';
        $attended=($_POST['attended']??'')===''?null:Validator::bool($_POST['attended']);
        $pdo=Database::connection();
        $s=$pdo->prepare('UPDATE event_registrations SET status=?,attended=? WHERE id=?');
        $s->execute([$status,$attended,$id]);
        if($s->rowCount()===0){$check=$pdo->prepare('SELECT 1 FROM event_registrations WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn()){http_response_code(404);exit('Iscrizione non trovata');}}
        Audit::log('campus.registration.update','event_registration',$id,['status'=>$status,'attended'=>$attended]);
        header('Location:/idemaclima/admin/campus/registrations');exit;
    }


    public static function markConfirmationSent(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();self::ensureRegistrationConfirmationColumn($pdo);
        $s=$pdo->prepare('UPDATE event_registrations SET confirmation_sent_at=NOW() WHERE id=?');$s->execute([$id]);
        if($s->rowCount()===0){$check=$pdo->prepare('SELECT 1 FROM event_registrations WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn()){http_response_code(404);exit('Iscrizione non trovata');}}
        Audit::log('campus.registration.confirmation_sent','event_registration',$id,['confirmation_sent_at'=>date('Y-m-d H:i:s')]);
        header('Location:/idemaclima/admin/campus/registrations');exit;
    }


    public static function catUsers(): void
    {
        AdminAuth::requireLogin();
        $status=(string)($_GET['status']??'all');$where=$status==='pending'?' WHERE active=0 AND verified_at IS NULL AND disabled_at IS NULL':'';
        $users=Database::connection()->query('SELECT * FROM cat_accounts'.$where.' ORDER BY company_name,contact_last_name')->fetchAll(PDO::FETCH_ASSOC);
        self::view('cat_users',['title'=>'Utenti CAT','users'=>$users]);
    }


    public static function catUserForm(): void
    {
        AdminAuth::requireLogin();
        $id=Validator::int($_GET['id']??0);
        $user=['id'=>0,'company_name'=>'','contact_first_name'=>'','contact_last_name'=>'','email'=>'','phone'=>'','username'=>'','active'=>1,'verified_at'=>''];
        if($id){
            $s=Database::connection()->prepare('SELECT id,company_name,contact_first_name,contact_last_name,email,phone,username,active,verified_at FROM cat_accounts WHERE id=?');
            $s->execute([$id]);
            $row=$s->fetch(PDO::FETCH_ASSOC);
            if(!$row){http_response_code(404);exit('Utente CAT non trovato');}
            $user=$row;
        }
        self::view('cat_user_form',['title'=>'Utente CAT','cat'=>$user,'errors'=>[]]);
    }


    public static function saveCatUser(): void
    {
        AdminAuth::requireLogin();self::csrf();
        $errors=[];$id=Validator::int($_POST['id']??0);
        $company=Validator::requiredString($_POST['company_name']??'','Azienda',190,$errors);
        $first=Validator::requiredString($_POST['contact_first_name']??'','Nome',120,$errors);
        $last=Validator::requiredString($_POST['contact_last_name']??'','Cognome',120,$errors);
        $email=strtolower(trim((string)($_POST['email']??'')));
        $username=trim((string)($_POST['username']??''));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>190)$errors[]='Email non valida.';
        if($username==='')$username=$email;
        if(str_contains($username,'@'))$username=strtolower($username);
        $usernameIsEmail=filter_var($username,FILTER_VALIDATE_EMAIL)!==false;
        $usernameIsAlias=preg_match('/^[A-Za-z0-9._-]+$/',$username)===1;
        if(mb_strlen($username)>120 || (!$usernameIsEmail && !$usernameIsAlias))$errors[]='Username non valido: inserisci un indirizzo email oppure usa lettere, numeri, punto, trattino o underscore.';
        $password=(string)($_POST['password']??'');
        if((!$id || $password!=='') && !self::strongPassword($password))$errors[]='La password deve avere almeno 12 caratteri e contenere maiuscola, minuscola, numero e simbolo.';
        $active=Validator::bool($_POST['active']??0);
        $phone=Validator::optionalString($_POST['phone']??'',50,'Telefono',$errors);
        $cat=['id'=>$id,'company_name'=>$company,'contact_first_name'=>$first,'contact_last_name'=>$last,'email'=>$email,'phone'=>$phone,'username'=>$username,'active'=>$active,'verified_at'=>''];
        if($errors){self::view('cat_user_form',['title'=>'Utente CAT','cat'=>$cat,'errors'=>$errors]);return;}
        $pdo=Database::connection();
        if($id){$check=$pdo->prepare('SELECT 1 FROM cat_accounts WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn()){http_response_code(404);exit('Utente CAT non trovato');}}
        try{
            if($id){
                if($password!==''){
                    $s=$pdo->prepare('UPDATE cat_accounts SET company_name=?,contact_first_name=?,contact_last_name=?,email=?,phone=?,username=?,password_hash=?,password_changed_at=NOW(),active=?,disabled_at=IF(?=1,NULL,NOW()) WHERE id=?');
                    $s->execute([$company,$first,$last,$email,$phone?:null,$username,password_hash($password,PASSWORD_DEFAULT),$active,$active,$id]);
                } else {
                    $s=$pdo->prepare('UPDATE cat_accounts SET company_name=?,contact_first_name=?,contact_last_name=?,email=?,phone=?,username=?,active=?,disabled_at=IF(?=1,NULL,NOW()) WHERE id=?');
                    $s->execute([$company,$first,$last,$email,$phone?:null,$username,$active,$active,$id]);
                }
                $entityId=$id;$action='cat_account.update';
            } else {
                $s=$pdo->prepare('INSERT INTO cat_accounts(company_name,contact_first_name,contact_last_name,email,phone,username,password_hash,password_changed_at,active,verified_at,disabled_at) VALUES(?,?,?,?,?,?,?,NOW(),?,NOW(),NULL)');
                $s->execute([$company,$first,$last,$email,$phone?:null,$username,password_hash($password,PASSWORD_DEFAULT),$active]);
                $entityId=(int)$pdo->lastInsertId();$action='cat_account.create';
            }
            Audit::log($action,'cat_account',$entityId,['company_name'=>$company,'email'=>$email,'active'=>$active]);
        }catch(\PDOException $e){
            if((string)$e->getCode()==='23000'){$errors[]='Email o username già utilizzato.';self::view('cat_user_form',['title'=>'Utente CAT','cat'=>$cat,'errors'=>$errors]);return;}
            throw $e;
        }
        header('Location:/idemaclima/admin/cat/users');exit;
    }


    private static function ensureRegistrationConfirmationColumn(PDO $pdo): void
    {
        $check=$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='event_registrations' AND COLUMN_NAME='confirmation_sent_at'");
        if((int)$check->fetchColumn()===0)$pdo->exec("ALTER TABLE event_registrations ADD COLUMN confirmation_sent_at DATETIME NULL AFTER privacy_accepted_at");
    }


    private static function defaultCover(string $audience): string
    {
        return $audience==='cat'
            ? '/idemaclima/public/brand-assets/campus-eventi-cat.webp.php'
            : '/idemaclima/public/brand-assets/campus-eventi-aperti.webp.php';
    }


    private static function strongPassword(string $password): bool
    {
        return strlen($password)>=12
            && preg_match('/[A-Z]/',$password)
            && preg_match('/[a-z]/',$password)
            && preg_match('/\d/',$password)
            && preg_match('/[^A-Za-z0-9]/',$password);
    }


    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/'.$file.'.php';}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
    private static function slugify(string $v):string{$a=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',strtolower(trim($v)))?:$v;return trim((string)preg_replace('/[^a-z0-9]+/','-',$a),'-');}
    private static function mysqlDateTime(mixed $value):?string{$v=trim((string)$value);if($v==='')return null;$dt=\DateTimeImmutable::createFromFormat('Y-m-d\TH:i',$v);if(!$dt||$dt->format('Y-m-d\TH:i')!==$v)return null;return $dt->format('Y-m-d H:i:s');}
}
