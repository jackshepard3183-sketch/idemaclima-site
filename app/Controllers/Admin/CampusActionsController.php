<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Upload;
use App\Core\Validator;
use App\Services\CampusMailService;
use PDO;

final class CampusActionsController
{
    public static function updateCatStatus():void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$action=(string)($_POST['action']??'');$pdo=Database::connection();
        $q=$pdo->prepare('SELECT email,contact_first_name,company_name FROM cat_accounts WHERE id=?');$q->execute([$id]);$cat=$q->fetch(PDO::FETCH_ASSOC);if(!$cat){http_response_code(404);exit('Utente CAT non trovato');}
        if($action==='approve')$sql='UPDATE cat_accounts SET active=1,verified_at=COALESCE(verified_at,NOW()),disabled_at=NULL WHERE id=?';
        elseif($action==='disable')$sql='UPDATE cat_accounts SET active=0,disabled_at=NOW() WHERE id=?';
        elseif($action==='reject')$sql='UPDATE cat_accounts SET active=0,verified_at=NULL,disabled_at=NOW() WHERE id=?';
        else{http_response_code(422);exit('Azione non valida');}
        $pdo->prepare($sql)->execute([$id]);Audit::log('cat_account.'.$action,'cat_account',$id,[]);
        if(in_array($action,['approve','reject'],true))CampusMailService::notifyParticipant((string)$cat['email'],$action==='approve'?'Accesso Campus CAT approvato':'Richiesta Campus CAT non approvata',$action==='approve'?"Ciao ".$cat['contact_first_name'].",\n\nla richiesta per ".$cat['company_name']." è stata approvata. Puoi accedere all’Area CAT con la tua email e la password scelta in registrazione.":"Ciao ".$cat['contact_first_name'].",\n\nla richiesta di accesso all’Area CAT non è stata approvata. Per chiarimenti contatta IDEMA Clima.");
        header('Location:/idemaclima/admin/cat/users');exit;
    }

    public static function duplicate(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT * FROM events WHERE id=?');$stmt->execute([$id]);$event=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$event){http_response_code(404);exit('Evento non trovato');}
        $slug=substr((string)$event['slug'].'-copia-'.date('YmdHis'),0,240);
        $cover=Upload::duplicateManaged($event['cover_image']??null);
        try{
            $copy=$pdo->prepare('INSERT INTO events(title,slug,audience,category,location,address,starts_at,ends_at,short_description,speaker,description,program,cover_image,max_seats,waitlist_enabled,registration_open,registration_deadline,fee_amount,fee_note,published,cancelled,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $copy->execute([(string)$event['title'].' - Copia',$slug,$event['audience'],$event['category'],$event['location'],$event['address'],$event['starts_at'],$event['ends_at'],$event['short_description'],$event['speaker'],$event['description'],$event['program'],$cover,$event['max_seats'],$event['waitlist_enabled'],0,$event['registration_deadline'],$event['fee_amount']??null,$event['fee_note']??null,0,0,$event['sort_order']]);
        }catch(\Throwable $e){if($cover && $cover!==($event['cover_image']??null))Upload::removeManaged($cover);throw $e;}
        $newId=(int)$pdo->lastInsertId();Audit::log('campus.event.duplicate','event',$newId,['source_id'=>$id]);
        header('Location:/idemaclima/admin/campus/events/form?id='.$newId);exit;
    }


    public static function delete(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);
        $returnAudience=in_array($_POST['return_audience']??'', ['public','cat'],true)?(string)$_POST['return_audience']:'';
        $pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT e.id,e.title,e.audience,e.cover_image,(SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id) registrations FROM events e WHERE e.id=?');
        $stmt->execute([$id]);$event=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$event){http_response_code(404);exit('Evento non trovato');}
        $pdo->beginTransaction();
        try{
            $delete=$pdo->prepare('DELETE FROM events WHERE id=?');
            $delete->execute([$id]);
            if($delete->rowCount()!==1)throw new \RuntimeException('Non è stato possibile eliminare l’evento.');
            $pdo->commit();
        }catch(\Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
        Audit::log('campus.event.delete','event',$id,[
            'title'=>(string)$event['title'],
            'audience'=>(string)$event['audience'],
            'registrations_deleted'=>(int)$event['registrations'],
        ]);
        Upload::removeManaged($event['cover_image']??null);
        $location='/idemaclima/admin/campus/events'.($returnAudience!==''?'?audience='.$returnAudience:'');
        header('Location:'.$location);exit;
    }

    public static function export(): void
    {
        AdminAuth::requireLogin();$eventId=Validator::int($_GET['event_id']??0);$catAccountId=Validator::int($_GET['cat_account_id']??0);$status=in_array($_GET['status']??'', ['registered','confirmed','waitlist','cancelled'],true)?(string)$_GET['status']:'';$where=[];$params=[];if($eventId){$where[]='e.id=?';$params[]=$eventId;}if($catAccountId){$where[]='r.cat_account_id=?';$params[]=$catAccountId;}if($status!==''){$where[]='r.status=?';$params[]=$status;}$sql='SELECT e.title,e.audience,r.first_name,r.last_name,r.email,r.phone,r.company,r.role,r.status,r.attended,r.certificate_number,r.certificate_issued_at,r.created_at FROM event_registrations r JOIN events e ON e.id=r.event_id'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY e.starts_at,r.last_name,r.first_name';
        $stmt=Database::connection()->prepare($sql);$stmt->execute($params);
        header('Content-Type:text/csv;charset=UTF-8');header('Content-Disposition:attachment;filename="iscrizioni-campus-'.date('Y-m-d').'.csv"');echo "\xEF\xBB\xBF";
        $out=fopen('php://output','wb');fputcsv($out,['Evento','Tipologia','Nome','Cognome','Email','Telefono','Azienda','Ruolo','Stato','Presenza','Attestato','Data attestato','Data iscrizione'],';');
        while($row=$stmt->fetch(PDO::FETCH_ASSOC)){fputcsv($out,[$row['title'],$row['audience']==='cat'?'CAT':'Aperto',$row['first_name'],$row['last_name'],$row['email'],$row['phone'],$row['company'],$row['role'],$row['status'],$row['attended']===null?'':((int)$row['attended']?'Presente':'Assente'),$row['certificate_number']??'', $row['certificate_issued_at']??'', $row['created_at']],';');}
        fclose($out);exit;
    }

    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
}
