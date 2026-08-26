<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use PDO;

final class ContactsAnalyticsController
{
    public static function contacts(): void
    {
        AdminAuth::requireLogin();
        $rows=Database::connection()->query('SELECT * FROM contact_submissions ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
        self::view('contacts',['title'=>'Contatti','rows'=>$rows]);
    }

    public static function contact(): void
    {
        AdminAuth::requireLogin();$id=(int)($_GET['id']??0);$s=Database::connection()->prepare('SELECT * FROM contact_submissions WHERE id=?');$s->execute([$id]);$row=$s->fetch(PDO::FETCH_ASSOC);if(!$row){http_response_code(404);exit('Richiesta non trovata');}self::view('contact_detail',['title'=>'Richiesta contatto','row'=>$row]);
    }

    public static function updateContact(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=(int)($_POST['id']??0);$status=in_array($_POST['status']??'', ['new','in_progress','closed','spam'],true)?$_POST['status']:'new';$notes=trim((string)($_POST['admin_notes']??''));
        if(mb_strlen($notes)>10000){http_response_code(422);exit('Note troppo lunghe');}
        $pdo=Database::connection();$check=$pdo->prepare('SELECT status FROM contact_submissions WHERE id=?');$check->execute([$id]);$before=$check->fetchColumn();if($before===false){http_response_code(404);exit('Richiesta non trovata');}
        $reviewedAt=$status==='new'?null:date('Y-m-d H:i:s');
        $s=$pdo->prepare('UPDATE contact_submissions SET status=?,admin_notes=?,reviewed_at=? WHERE id=?');$s->execute([$status,$notes?:null,$reviewedAt,$id]);
        Audit::log('contact.update','contact_submission',$id,['status_from'=>$before,'status_to'=>$status]);
        header('Location:/admin/contacts/view?id='.$id);exit;
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
        AdminAuth::requireLogin();$pdo=Database::connection();
        $totals=$pdo->query("SELECT event_type,COUNT(*) total FROM document_events GROUP BY event_type")->fetchAll(PDO::FETCH_KEY_PAIR);
        $top=$pdo->query("SELECT d.id,d.title,COUNT(*) downloads FROM document_events e JOIN documents d ON d.id=e.document_id WHERE e.event_type='download' GROUP BY d.id,d.title ORDER BY downloads DESC LIMIT 25")->fetchAll(PDO::FETCH_ASSOC);
        $settings=$pdo->query('SELECT * FROM analytics_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC)?:[];
        self::view('analytics',['title'=>'Statistiche','totals'=>$totals,'top'=>$top,'settings'=>$settings]);
    }

    public static function saveAnalytics(): void
    {
        AdminAuth::requireLogin();self::csrf();$measurement=strtoupper(trim((string)($_POST['ga4_measurement_id']??'')));$property=trim((string)($_POST['ga4_property_id']??''));$enabled=isset($_POST['analytics_enabled'])?1:0;$consent=isset($_POST['consent_required'])?1:0;$pdf=isset($_POST['pdf_tracking_enabled'])?1:0;$retention=(int)($_POST['internal_tracking_retention_days']??180);
        $errors=[];
        if($measurement!==''&&!preg_match('/^G-[A-Z0-9]{6,20}$/',$measurement))$errors[]='Measurement ID GA4 non valido.';
        if($property!==''&&!preg_match('/^[0-9]{4,20}$/',$property))$errors[]='Property ID GA4 non valido.';
        if($retention<1||$retention>3650)$errors[]='Conservazione statistiche: inserisci un valore tra 1 e 3650 giorni.';
        if($enabled&&$measurement==='')$errors[]='Inserisci il Measurement ID prima di attivare GA4.';
        if($errors){http_response_code(422);exit(implode("\n",$errors));}
        Database::connection()->prepare('UPDATE analytics_settings SET ga4_measurement_id=?,ga4_property_id=?,analytics_enabled=?,consent_required=?,pdf_tracking_enabled=?,internal_tracking_retention_days=? WHERE id=1')->execute([$measurement?:null,$property?:null,$enabled,$consent,$pdf,$retention]);
        Audit::log('analytics.settings','analytics_settings',1,['analytics_enabled'=>$enabled,'consent_required'=>$consent,'pdf_tracking_enabled'=>$pdf,'retention_days'=>$retention]);
        header('Location:/admin/analytics');exit;
    }

    private static function view(string $file,array $data):void{extract($data,EXTR_SKIP);$user=AdminAuth::user();$csrf=Security::csrfToken();require dirname(__DIR__,2).'/Views/admin/'.$file.'.php';}
    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
}
