<?php


declare(strict_types=1);


namespace App\Controllers\Admin;


use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;


final class ContactsAnalyticsController
{
    public static function contacts(): void
    {
        AdminAuth::requireLogin();
        $status=(string)($_GET['status']??'');$allowed=['new','in_progress','closed','spam'];$pdo=Database::connection();self::backfillContactLocations($pdo);
        if(in_array($status,$allowed,true)){$s=$pdo->prepare('SELECT * FROM contact_submissions WHERE status=? ORDER BY created_at DESC');$s->execute([$status]);$rows=$s->fetchAll(PDO::FETCH_ASSOC);}else{$rows=$pdo->query('SELECT * FROM contact_submissions ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);}
        self::view('contacts',['title'=>'Contatti','rows'=>$rows,'status'=>$status]);
    }


    public static function contact(): void
    {
        AdminAuth::requireLogin();$id=(int)($_GET['id']??0);$pdo=Database::connection();self::backfillContactLocations($pdo);$s=$pdo->prepare('SELECT * FROM contact_submissions WHERE id=?');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row){http_response_code(404);exit('Richiesta non trovata');}$replyLog=$pdo->prepare("SELECT created_at FROM audit_log WHERE action='contact.reply_sent' AND entity_type='contact_submission' AND entity_id=? ORDER BY created_at DESC LIMIT 1");$replyLog->execute([$id]);$replySentAt=$replyLog->fetchColumn()?:null;self::view('contact_detail',['title'=>'Richiesta contatto','row'=>$row,'replySentAt'=>$replySentAt]);
    }


    public static function updateContact(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=(int)($_POST['id']??0);$status=in_array($_POST['status']??'', ['new','in_progress','closed','spam'],true)?$_POST['status']:'new';$notes=trim((string)($_POST['admin_notes']??''));
        if(mb_strlen($notes)>10000){http_response_code(422);exit('Note troppo lunghe');}
        $pdo=Database::connection();$check=$pdo->prepare('SELECT status FROM contact_submissions WHERE id=?');$check->execute([$id]);$before=$check->fetchColumn();if($before===false){http_response_code(404);exit('Richiesta non trovata');}
        $reviewedAt=$status==='new'?null:date('Y-m-d H:i:s');
        $s=$pdo->prepare('UPDATE contact_submissions SET status=?,admin_notes=?,reviewed_at=? WHERE id=?');$s->execute([$status,$notes?:null,$reviewedAt,$id]);
        Audit::log('contact.update','contact_submission',$id,['status_from'=>$before,'status_to'=>$status]);
        header('Location:/idemaclima/admin/contacts/view?id='.$id);exit;
    }


    public static function markReplySent(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=(int)($_POST['id']??0);$pdo=Database::connection();$check=$pdo->prepare('SELECT 1 FROM contact_submissions WHERE id=?');$check->execute([$id]);if(!$check->fetchColumn()){http_response_code(404);exit('Richiesta non trovata');}
        Audit::log('contact.reply_sent','contact_submission',$id);
        header('Location:/idemaclima/admin/contacts/view?id='.$id);exit;
    }


    public static function attachment(string $id): void
    {
        AdminAuth::requireLogin();$s=Database::connection()->prepare('SELECT attachment_path,attachment_name,attachment_mime FROM contact_submissions WHERE id=?');$s->execute([(int)$id]);$r=$s->fetch(PDO::FETCH_ASSOC);if(!$r||empty($r['attachment_path'])){http_response_code(404);exit('File non trovato');}$root=realpath(dirname(__DIR__,3).'/storage/private');$path=realpath(dirname(__DIR__,3).'/storage/private/'.ltrim((string)$r['attachment_path'],'/'));if(!$root||!$path||!str_starts_with($path,$root.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(404);exit('File non trovato');}
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        $name=preg_replace('/[^A-Za-z0-9._-]+/','-',basename((string)$r['attachment_name']))?:'allegato';
        header('Content-Type: '.$mime);header('Content-Disposition: attachment; filename="'.substr($name,0,180).'"');header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store');header('Pragma: no-cache');header('X-Content-Type-Options: nosniff');readfile($path);exit;
    }


    public static function analytics(): void
    {
        AdminAuth::requireLogin();$pdo=Database::connection();self::ensureIntegrationColumns($pdo);
        $totals=$pdo->query("SELECT event_type,COUNT(*) total FROM document_events GROUP BY event_type")->fetchAll(PDO::FETCH_KEY_PAIR);
        $top=$pdo->query("SELECT d.id,d.title,COUNT(*) downloads FROM document_events e JOIN documents d ON d.id=e.document_id WHERE e.event_type='download' GROUP BY d.id,d.title ORDER BY downloads DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
        $settings=$pdo->query('SELECT * FROM analytics_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC)?:[];
        self::view('analytics',['title'=>'Analytics e integrazioni','totals'=>$totals,'top'=>$top,'settings'=>$settings]);
    }


    public static function saveAnalytics(): void
    {
        AdminAuth::requireLogin();
        self::ensureIntegrationColumns(Database::connection());
        AdminAuth::requireLogin();self::csrf();$measurement=strtoupper(trim((string)($_POST['ga4_measurement_id']??'')));$property=trim((string)($_POST['ga4_property_id']??''));$gtm=strtoupper(trim((string)($_POST['gtm_container_id']??'')));$searchConsole=trim((string)($_POST['search_console_verification']??''));$metaPixel=trim((string)($_POST['meta_pixel_id']??''));$iubendaSite=trim((string)($_POST['iubenda_site_id']??''));$iubendaPolicy=trim((string)($_POST['iubenda_cookie_policy_id']??'38092343'));$iubendaEnabled=isset($_POST['iubenda_enabled'])?1:0;$enabled=isset($_POST['analytics_enabled'])?1:0;$consent=isset($_POST['consent_required'])?1:0;$pdf=isset($_POST['pdf_tracking_enabled'])?1:0;$retention=(int)($_POST['internal_tracking_retention_days']??180);
        $errors=[];
        if($measurement!==''&&!preg_match('/^G-[A-Z0-9]{6,20}$/',$measurement))$errors[]='Measurement ID GA4 non valido.';
        if($property!==''&&!preg_match('/^[0-9]{4,20}$/',$property))$errors[]='Property ID GA4 non valido.';
        if($gtm!==''&&!preg_match('/^GTM-[A-Z0-9]{4,20}$/',$gtm))$errors[]='Container ID Google Tag Manager non valido.';
        if($searchConsole!==''&&!preg_match('/^[A-Za-z0-9_-]{8,255}$/',$searchConsole))$errors[]='Codice di verifica Search Console non valido.';
        if($metaPixel!==''&&!preg_match('/^[0-9]{5,32}$/',$metaPixel))$errors[]='ID Meta Pixel non valido.';
        if($iubendaSite!==''&&!preg_match('/^[0-9]{3,32}$/',$iubendaSite))$errors[]='Site ID iubenda non valido.';
        if(!preg_match('/^[0-9]{3,32}$/',$iubendaPolicy))$errors[]='Cookie Policy ID iubenda non valido.';
        if($retention<1||$retention>3650)$errors[]='Conservazione statistiche: inserisci un valore tra 1 e 3650 giorni.';
        if($enabled&&$measurement===''&&$gtm==='')$errors[]='Inserisci il Measurement ID GA4 o il Container ID GTM prima di attivare il tracking.';
        if($iubendaEnabled&&$iubendaSite==='')$errors[]='Inserisci il Site ID prima di attivare iubenda CMP.';
        if($enabled&&$consent&&!$iubendaEnabled)$errors[]='Per attivare servizi soggetti a consenso devi prima configurare e attivare iubenda CMP.';
        if($errors){http_response_code(422);exit(implode("\n",$errors));}
        Database::connection()->prepare('UPDATE analytics_settings SET ga4_measurement_id=?,ga4_property_id=?,gtm_container_id=?,search_console_verification=?,meta_pixel_id=?,iubenda_enabled=?,iubenda_site_id=?,iubenda_cookie_policy_id=?,analytics_enabled=?,consent_required=?,pdf_tracking_enabled=?,internal_tracking_retention_days=? WHERE id=1')->execute([$measurement?:null,$property?:null,$gtm?:null,$searchConsole?:null,$metaPixel?:null,$iubendaEnabled,$iubendaSite?:null,$iubendaPolicy,$enabled,$consent,$pdf,$retention]);
        Audit::log('analytics.settings','analytics_settings',1,['analytics_enabled'=>$enabled,'iubenda_enabled'=>$iubendaEnabled,'consent_required'=>$consent,'pdf_tracking_enabled'=>$pdf,'retention_days'=>$retention]);
        header('Location:/idemaclima/admin/analytics');exit;
    }


    private static function backfillContactLocations(PDO $pdo):void
    {
        $path=dirname(__DIR__,3).'/public/locations-cascade.json';
        if(!is_file($path))return;
        $locations=json_decode((string)file_get_contents($path),true);
        if(!is_array($locations))return;
        $normalize=static fn(string $value):string=>mb_strtoupper(trim((string)preg_replace('/\s+/u',' ',$value)),'UTF-8');
        $rows=$pdo->query("SELECT id,region,province,city,postal_code FROM contact_submissions WHERE COALESCE(region,'')='' OR COALESCE(province,'')='' OR COALESCE(city,'')='' OR COALESCE(postal_code,'')=''")->fetchAll(PDO::FETCH_ASSOC);
        $update=$pdo->prepare('UPDATE contact_submissions SET region=?,province=?,city=?,postal_code=? WHERE id=?');
        foreach($rows as $row){
            $wantedRegion=$normalize((string)($row['region']??''));
            $wantedProvince=$normalize((string)($row['province']??''));
            $wantedCity=$normalize((string)($row['city']??''));
            if($wantedCity==='')continue;
            $matches=[];
            foreach($locations as $region=>$provinces){
                if($wantedRegion!==''&&$normalize((string)$region)!==$wantedRegion)continue;
                if(!is_array($provinces))continue;
                foreach($provinces as $province=>$cities){
                    $provinceCode=Validator::provinceCode((string)$province);
                    if($wantedProvince!==''&&$normalize((string)$province)!==$wantedProvince&&$normalize($provinceCode)!==$wantedProvince)continue;
                    if(!is_array($cities))continue;
                    foreach($cities as $city=>$caps){
                        if($normalize((string)$city)!==$wantedCity)continue;
                        $matches[]=[(string)$region,$provinceCode,(string)$city,is_array($caps)?array_values(array_filter(array_map('strval',$caps))):[]];
                    }
                }
            }
            if(count($matches)!==1)continue;
            [$region,$province,$city,$caps]=$matches[0];
            $postal=trim((string)($row['postal_code']??''));
            if($postal===''&&!empty($caps))$postal=$caps[0];
            $update->execute([
                trim((string)($row['region']??''))!==''?(string)$row['region']:$region,
                trim((string)($row['province']??''))!==''?(string)$row['province']:$province,
                trim((string)($row['city']??''))!==''?(string)$row['city']:$city,
                $postal!==''?$postal:null,
                (int)$row['id'],
            ]);
        }
    }


    private static function ensureIntegrationColumns(PDO $pdo):void
    {
        $columns=['gtm_container_id'=>'VARCHAR(32) NULL','search_console_verification'=>'VARCHAR(255) NULL','meta_pixel_id'=>'VARCHAR(32) NULL','iubenda_enabled'=>'TINYINT(1) NOT NULL DEFAULT 0','iubenda_site_id'=>'VARCHAR(32) NULL','iubenda_cookie_policy_id'=>"VARCHAR(32) NOT NULL DEFAULT '38092343'"];
        $existing=array_map('strval',$pdo->query('SHOW COLUMNS FROM analytics_settings')->fetchAll(PDO::FETCH_COLUMN));
        foreach($columns as $name=>$definition)if(!in_array($name,$existing,true))$pdo->exec('ALTER TABLE analytics_settings ADD COLUMN '.$name.' '.$definition);
    }


    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/'.$file.'.php';}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
}
