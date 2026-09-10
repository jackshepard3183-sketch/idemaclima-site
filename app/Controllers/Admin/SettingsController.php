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
                if(($meta['type']??'')==='url'&&$value!==''&&!filter_var($value,FILTER_VALIDATE_URL))throw new \RuntimeException('Indirizzo non valido: '.$meta['label'].'.');
                $upsert->execute([$group,$key,$value,(int)($admin['id']??0)]);
            }
            Audit::log('settings.update','site_settings',null,['group'=>$group]);$pdo->commit();
        }catch(\Throwable $e){if($pdo->inTransaction())$pdo->rollBack();http_response_code(422);exit(htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'));}
        header('Location:/idemaclima/admin/settings/'.$group.'?saved=1');exit;
    }
    public static function users():void
    {
        AdminAuth::requireLogin();$rows=Database::connection()->query('SELECT id,first_name,last_name,email,username,role,active,last_login_at FROM admin_users ORDER BY last_name,first_name')->fetchAll(PDO::FETCH_ASSOC);
        self::view('settings_users',['title'=>'Utenti e accessi','rows'=>$rows]);
    }
    public static function system():void
    {
        AdminAuth::requireLogin();$user=AdminAuth::user();
        if(in_array((string)($user['role']??''),['content','requests'],true)){http_response_code(403);exit('Accesso riservato agli amministratori tecnici.');}
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
                'contacts_recipients'=>['label'=>'Destinatari Contatti','type'=>'text','max'=>1000],
                'warranty_recipients'=>['label'=>'Destinatari Garanzia','type'=>'text','max'=>1000],
                'incentives_recipients'=>['label'=>'Destinatari Detrazioni','type'=>'text','max'=>1000],
                'campus_recipients'=>['label'=>'Destinatari Campus','type'=>'text','max'=>1000],
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
    private static function ensureSchema():void
    {
        Database::connection()->exec("CREATE TABLE IF NOT EXISTS site_settings (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,setting_group VARCHAR(50) NOT NULL,setting_key VARCHAR(100) NOT NULL,setting_value TEXT NULL,updated_by BIGINT UNSIGNED NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,UNIQUE KEY uq_site_setting(setting_group,setting_key),KEY idx_site_settings_group(setting_group)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
    private static function title(string $group):string{return ['general'=>'Impostazioni generali','email'=>'Email e notifiche','site'=>'Impostazioni sito'][$group]??'Impostazioni';}
    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/idemaclima/admin/'.$file.'.php';}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
}
