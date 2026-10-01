<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use PDO;

final class SettingsController
{
    private const GROUPS=['general','email','site'];
    public static function page(string $group):void
    {
        AdminAuth::requireLogin();
        if(!in_array($group,self::GROUPS,true)){http_response_code(404);exit('Sezione non trovata');}
        self::ensureSchema();$pdo=Database::connection();
        $s=$pdo->prepare('SELECT setting_key,setting_value FROM site_settings WHERE setting_group=? ORDER BY setting_key');$s->execute([$group]);
        $settings=$s->fetchAll(PDO::FETCH_KEY_PAIR);
        self::view('settings',['title'=>self::title($group),'group'=>$group,'settings'=>$settings,'saved'=>isset($_GET['saved'])]);
    }
    public static function save(string $group):void
    {
        AdminAuth::requireLogin();self::csrf();
        if(!in_array($group,self::GROUPS,true)){http_response_code(404);exit('Sezione non trovata');}
        self::ensureSchema();$allowed=self::fields($group);$pdo=Database::connection();
        $upsert=$pdo->prepare('INSERT INTO site_settings(setting_group,setting_key,setting_value,updated_by) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by),updated_at=CURRENT_TIMESTAMP');
        $admin=AdminAuth::user();$pdo->beginTransaction();
        try{
            foreach($allowed as $key=>$meta){
                $value=$meta['type']==='bool'?(isset($_POST[$key])?'1':'0'):trim((string)($_POST[$key]??''));
                if(mb_strlen($value)>(int)($meta['max']??2000))throw new \RuntimeException('Il valore di '.$meta['label'].' è troppo lungo.');
                if(($meta['type']??'')==='email'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_EMAIL))throw new \RuntimeException('Indirizzo email non valido: '.$meta['label'].'.');
                if(($meta['type']??'')==='emaillist'&&$value!==''){
                    $emails=preg_split('/[;,\s]+/',trim($value))?:[];
                    $emails=array_values(array_unique(array_filter(array_map('trim',$emails))));
                    if(!$emails)throw new \RuntimeException('Inserisci almeno un destinatario per '.$meta['label'].'.');
                    foreach($emails as $email){if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new \RuntimeException('Indirizzo email non valido in '.$meta['label'].': '.$email);}
                    $value=implode(', ',$emails);
                }
                if(($meta['type']??'')==='url'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_URL))throw new \RuntimeException('Indirizzo non valido: '.$meta['label'].'.');
                $upsert->execute([$group,$key,$value,(int)($admin['id']??0)]);
            }
            Audit::log('settings.update','site_settings',null,['group'=>$group]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
        header('Location:/idemaclima/admin/settings/'.$group.'?saved=1');exit;
    }
    public static function account():void
    {
        $user=AdminAuth::user();
        if($user===null){header('Location:/idemaclima/admin/login',true,302);exit;}
        self::view('account',['title'=>'Il mio account','saved'=>isset($_GET['saved']),'error'=>'']);
    }
    public static function savePassword():void
    {
        $user=AdminAuth::user();
        if($user===null){header('Location:/idemaclima/admin/login',true,302);exit;}
        self::csrf();
        $current=(string)($_POST['current_password']??'');
        $password=(string)($_POST['new_password']??'');
        $confirmation=(string)($_POST['new_password_confirmation']??'');
        $error='';
        if(!AdminAuth::verifyPassword((int)$user['id'],$current))$error='La password attuale non è corretta.';
        elseif(strlen($password)<12)$error='La nuova password deve contenere almeno 12 caratteri.';
        elseif($password!==$confirmation)$error='Le due nuove password non coincidono.';
        elseif(hash_equals($current,$password))$error='La nuova password deve essere diversa da quella attuale.';
        if($error!==''){http_response_code(422);self::view('account',['title'=>'Il mio account','saved'=>false,'error'=>$error]);return;}
        Database::connection()->prepare('UPDATE admin_users SET password_hash=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),(int)$user['id']]);
        Audit::log('admin_user.password_change','admin_user',(int)$user['id'],[]);
        header('Location:/idemaclima/admin/account?saved=1');exit;
    }

    public static function users():void
    {
        AdminAuth::requireManager();self::ensureRolesSchema();$pdo=Database::connection();$rows=$pdo->query('SELECT id,first_name,last_name,email,username,role,active,last_login_at FROM admin_users ORDER BY last_name,first_name')->fetchAll(PDO::FETCH_ASSOC);
        $roles=$pdo->query('SELECT slug,name,is_system FROM admin_roles ORDER BY is_system DESC,name')->fetchAll(PDO::FETCH_ASSOC);$permissions=[];foreach($pdo->query('SELECT role_slug,section_key FROM admin_role_permissions')->fetchAll(PDO::FETCH_ASSOC) as $p){$permissions[$p['role_slug']][]=$p['section_key'];}
        self::view('settings_users',['title'=>'Utenti e accessi','rows'=>$rows,'roles'=>$roles,'permissions'=>$permissions,'sections'=>self::permissionSections(),'saved'=>isset($_GET['saved']),'roleSaved'=>isset($_GET['role_saved'])]);
    }
    public static function userForm():void
    {
        AdminAuth::requireManager();self::ensureRolesSchema();$id=max(0,(int)($_GET['id']??0));
        $row=['id'=>0,'first_name'=>'','last_name'=>'','email'=>'','username'=>'','role'=>'content','active'=>1];
        if($id){$s=Database::connection()->prepare('SELECT id,first_name,last_name,email,username,role,active FROM admin_users WHERE id=?');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC)?:null;if(!$row){http_response_code(404);exit('Utente non trovato');}}
        $roles=Database::connection()->query('SELECT slug,name FROM admin_roles ORDER BY is_system DESC,name')->fetchAll(PDO::FETCH_ASSOC);self::view('settings_user_form',['title'=>$id?'Modifica utente':'Nuovo utente','row'=>$row,'roles'=>$roles]);
    }
    public static function saveUser():void
    {
        $manager=AdminAuth::requireManager();self::csrf();self::ensureRolesSchema();$pdo=Database::connection();if(($_POST['_entity']??'user')==='role'){self::saveRole($pdo);return;}$id=max(0,(int)($_POST['id']??0));$errors=[];
        $first=trim((string)($_POST['first_name']??''));$last=trim((string)($_POST['last_name']??''));$email=strtolower(trim((string)($_POST['email']??'')));$username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');$role=(string)($_POST['role']??'content');$active=isset($_POST['active'])?1:0;
        if($first===''||mb_strlen($first)>120)$errors[]='Nome obbligatorio o troppo lungo.';if($last===''||mb_strlen($last)>120)$errors[]='Cognome obbligatorio o troppo lungo.';if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190)$errors[]='Email non valida.';if(!preg_match('/^[A-Za-z0-9._-]{3,80}$/',$username))$errors[]='Username non valido.';$rs=$pdo->prepare('SELECT COUNT(*) FROM admin_roles WHERE slug=?');$rs->execute([$role]);if(!(bool)$rs->fetchColumn())$errors[]='Ruolo non valido.';if(($id===0&&strlen($password)<12)||($password!==''&&strlen($password)<12))$errors[]='La password deve contenere almeno 12 caratteri.';if($id===(int)$manager['id']&&!$active)$errors[]='Non puoi disabilitare il tuo account.';
        if($errors){http_response_code(422);exit(htmlspecialchars(implode(' ',$errors),ENT_QUOTES,'UTF-8'));}
        try{
            if($id){$sql='UPDATE admin_users SET first_name=?,last_name=?,email=?,username=?,role=?,active=?'.($password!==''?',password_hash=?':'').' WHERE id=?';$params=[$first,$last,$email,$username,$role,$active];if($password!=='')$params[]=password_hash($password,PASSWORD_DEFAULT);$params[]=$id;$pdo->prepare($sql)->execute($params);}
            else{$pdo->prepare('INSERT INTO admin_users(first_name,last_name,email,username,password_hash,role,active) VALUES(?,?,?,?,?,?,?)')->execute([$first,$last,$email,$username,password_hash($password,PASSWORD_DEFAULT),$role,$active]);$id=(int)$pdo->lastInsertId();}
            Audit::log('admin_user.save','admin_user',$id,['role'=>$role,'active'=>$active]);
        }catch(\PDOException $e){if((string)$e->getCode()==='23000'){http_response_code(422);exit('Email o username già utilizzato.');}throw $e;}
        header('Location:/idemaclima/admin/settings/users?saved=1');exit;
    }
    public static function system():void
    {
        $user=AdminAuth::requireManager();
        $pdo=Database::connection();$databaseVersion='Non disponibile';
        try{$databaseVersion=(string)$pdo->query('SELECT VERSION()')->fetchColumn();}catch(\Throwable){}
        $upload=dirname(__DIR__,3).'/public/uploads';$private=dirname(__DIR__,3).'/storage/private';
        $diagnostics=['Versione sito'=>(string)(getenv('APP_VERSION')?:'staging'),'Versione PHP'=>PHP_VERSION,'Versione database'=>$databaseVersion,'Invio email'=>function_exists('mail')?'Disponibile':'Non disponibile','Cartella upload'=>(is_dir($upload)&&is_writable($upload))?'Scrivibile':'Da verificare','Archivio privato'=>(is_dir($private)&&is_writable($private))?'Scrivibile':'Da verificare','Ambiente'=>(string)(getenv('APP_ENV')?:'production')];
        self::view('settings_system',['title'=>'Sistema','diagnostics'=>$diagnostics]);
    }
    public static function fields(string $group):array
    {
        return match($group){
            'general'=>[
                'company_name'=>['label'=>'Nome azienda','type'=>'text','max'=>190],
                'registered_office'=>['label'=>'Sede legale','type'=>'text','max'=>500],
                'operational_office'=>['label'=>'Sede amministrativa','type'=>'text','max'=>500],
                'phone'=>['label'=>'Telefono','type'=>'text','max'=>80],
                'email'=>['label'=>'Email','type'=>'email','max'=>190],
                'pec'=>['label'=>'PEC','type'=>'email','max'=>190],
                'company_data'=>['label'=>'Dati societari','type'=>'textarea','max'=>2000],
                'facebook_url'=>['label'=>'Facebook','type'=>'url','max'=>500],
                'instagram_url'=>['label'=>'Instagram','type'=>'url','max'=>500],
                'linkedin_url'=>['label'=>'LinkedIn','type'=>'url','max'=>500],
            ],
            'email'=>[
                'sender_name'=>['label'=>'Nome mittente','type'=>'text','max'=>190],
                'sender_email'=>['label'=>'Email mittente','type'=>'email','max'=>190],
                'contacts_recipients'=>['label'=>'Destinatari Contatti','type'=>'emaillist','max'=>1000],
                'warranty_recipients'=>['label'=>'Destinatari Garanzia','type'=>'emaillist','max'=>1000],
                'incentives_recipients'=>['label'=>'Destinatari Detrazioni','type'=>'emaillist','max'=>1000],
                'campus_recipients'=>['label'=>'Destinatari Campus','type'=>'emaillist','max'=>1000],
                'notifications_enabled'=>['label'=>'Notifiche email attive','type'=>'bool','max'=>1],
            ],
            'site'=>[
                'copyright'=>['label'=>'Copyright','type'=>'text','max'=>500],
                'privacy_url'=>['label'=>'Link Privacy Policy','type'=>'url','max'=>500],
                'cookie_url'=>['label'=>'Link Cookie Policy','type'=>'url','max'=>500],
                'price_list_url'=>['label'=>'Link listino prezzi','type'=>'url','max'=>500],
                'seo_title'=>['label'=>'Titolo SEO generale','type'=>'text','max'=>190],
                'seo_description'=>['label'=>'Descrizione SEO generale','type'=>'textarea','max'=>500],
                'maintenance_mode'=>['label'=>'Modalità manutenzione','type'=>'bool','max'=>1],
            ],
            default=>[]
        };
    }
    public static function permissionSections():array
    {
        return ['dashboard'=>'Dashboard','media'=>'Media Library','catalogs'=>'Cataloghi','references'=>'Referenze','technical'=>'Schede tecniche','assistance'=>'Assistenza','campus'=>'Campus','warranties'=>'Garanzie','incentives'=>'Detrazioni e incentivi','contacts'=>'Contatti','settings'=>'Impostazioni generali','users'=>'Utenti e accessi','analytics'=>'Analytics e integrazioni','redirects'=>'Redirect SEO'];
    }
    private static function ensureRolesSchema():void
    {
        $pdo=Database::connection();$pdo->exec("CREATE TABLE IF NOT EXISTS admin_roles (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,slug VARCHAR(80) NOT NULL UNIQUE,name VARCHAR(120) NOT NULL,is_system TINYINT(1) NOT NULL DEFAULT 0,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin_role_permissions (role_slug VARCHAR(80) NOT NULL,section_key VARCHAR(80) NOT NULL,PRIMARY KEY(role_slug,section_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $seed=$pdo->prepare('INSERT IGNORE INTO admin_roles(slug,name,is_system) VALUES(?,?,1)');foreach([['administrator','Amministratore completo'],['content','Gestione contenuti'],['requests','Gestione richieste']] as $r)$seed->execute($r);
        $count=(int)$pdo->query('SELECT COUNT(*) FROM admin_role_permissions')->fetchColumn();if($count===0){$ins=$pdo->prepare('INSERT IGNORE INTO admin_role_permissions(role_slug,section_key) VALUES(?,?)');$defaults=['content'=>['dashboard','media','catalogs','references','technical','assistance','campus'],'requests'=>['dashboard','campus','warranties','incentives','contacts']];foreach($defaults as $role=>$sections)foreach($sections as $section)$ins->execute([$role,$section]);}
    }
    private static function saveRole(PDO $pdo):void
    {
        $action=(string)($_POST['role_action']??'save');$original=trim((string)($_POST['original_slug']??''));$name=trim((string)($_POST['role_name']??''));$slug=strtolower(trim((string)($_POST['role_slug']??'')));
        if($action==='delete'){if($original===''||in_array($original,['admin','administrator','content','requests'],true)){http_response_code(422);exit('Questa tipologia non può essere eliminata.');}$q=$pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE role=?');$q->execute([$original]);if((int)$q->fetchColumn()>0){http_response_code(422);exit('La tipologia è assegnata a uno o più utenti. Modifica prima gli utenti interessati.');}$pdo->prepare('DELETE FROM admin_role_permissions WHERE role_slug=?')->execute([$original]);$pdo->prepare('DELETE FROM admin_roles WHERE slug=?')->execute([$original]);Audit::log('admin_role.delete','admin_role',null,['slug'=>$original]);header('Location:/idemaclima/admin/settings/users?role_saved=1');exit;}
        if(!preg_match('/^[a-z0-9_-]{3,80}$/',$slug)||$name===''||mb_strlen($name)>120){http_response_code(422);exit('Nome o codice tipologia non valido.');}
        if($original!==''&&$original!==$slug){http_response_code(422);exit('Il codice di una tipologia esistente non può essere modificato.');}
        $pdo->prepare('INSERT INTO admin_roles(slug,name,is_system) VALUES(?,?,0) ON DUPLICATE KEY UPDATE name=VALUES(name)')->execute([$slug,$name]);$pdo->prepare('DELETE FROM admin_role_permissions WHERE role_slug=?')->execute([$slug]);$ins=$pdo->prepare('INSERT INTO admin_role_permissions(role_slug,section_key) VALUES(?,?)');$valid=array_keys(self::permissionSections());foreach((array)($_POST['permissions']??[]) as $section)if(in_array($section,$valid,true))$ins->execute([$slug,$section]);Audit::log('admin_role.save','admin_role',null,['slug'=>$slug]);header('Location:/idemaclima/admin/settings/users?role_saved=1');exit;
    }
    private static function ensureSchema():void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS site_settings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,setting_group VARCHAR(50) NOT NULL,setting_key VARCHAR(100) NOT NULL,setting_value TEXT NULL,updated_by BIGINT UNSIGNED NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_site_setting(setting_group,setting_key),KEY idx_site_settings_group(setting_group)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    private static function title(string $group):string{return ['general'=>'Impostazioni generali','email'=>'Email e notifiche','site'=>'Impostazioni sito'][$group]??'Impostazioni';}
    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/'.$file.'.php';}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
}