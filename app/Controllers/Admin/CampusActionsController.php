<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Audit;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;

final class CampusActionsController
{
    public static function duplicate(): void
    {
        AdminAuth::requireLogin();self::csrf();$id=Validator::int($_POST['id']??0);$pdo=Database::connection();
        $stmt=$pdo->prepare('SELECT * FROM events WHERE id=?');$stmt->execute([$id]);$event=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$event){http_response_code(404);exit('Evento non trovato');}
        $slug=substr((string)$event['slug'].'-copia-'.date('YmdHis'),0,240);
        $copy=$pdo->prepare('INSERT INTO events(title,slug,audience,category,location,address,starts_at,ends_at,short_description,speaker,description,program,cover_image,max_seats,waitlist_enabled,registration_open,registration_deadline,published,cancelled,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $copy->execute([(string)$event['title'].' - Copia',$slug,$event['audience'],$event['category'],$event['location'],$event['address'],$event['starts_at'],$event['ends_at'],$event['short_description'],$event['speaker'],$event['description'],$event['program'],$event['cover_image'],$event['max_seats'],$event['waitlist_enabled'],0,$event['registration_deadline'],0,0,$event['sort_order']]);
        $newId=(int)$pdo->lastInsertId();Audit::log('campus.event.duplicate','event',$newId,['source_id'=>$id]);
        header('Location:/admin/campus/events/form?id='.$newId);exit;
    }

    public static function export(): void
    {
        AdminAuth::requireLogin();$eventId=Validator::int($_GET['event_id']??0);$sql='SELECT e.title,e.audience,r.first_name,r.last_name,r.email,r.phone,r.company,r.role,r.status,r.attended,r.created_at FROM event_registrations r JOIN events e ON e.id=r.event_id'.($eventId?' WHERE e.id=?':'').' ORDER BY e.starts_at,r.last_name,r.first_name';
        $stmt=Database::connection()->prepare($sql);$stmt->execute($eventId?[$eventId]:[]);
        header('Content-Type:text/csv;charset=UTF-8');header('Content-Disposition:attachment;filename="iscrizioni-campus-'.date('Y-m-d').'.csv"');echo "\xEF\xBB\xBF";
        $out=fopen('php://output','wb');fputcsv($out,['Evento','Tipologia','Nome','Cognome','Email','Telefono','Azienda','Ruolo','Stato','Presenza','Data iscrizione'],';');
        while($row=$stmt->fetch(PDO::FETCH_ASSOC)){fputcsv($out,[$row['title'],$row['audience']==='cat'?'CAT':'Aperto',$row['first_name'],$row['last_name'],$row['email'],$row['phone'],$row['company'],$row['role'],$row['status'],$row['attended']===null?'':((int)$row['attended']?'Presente':'Assente'),$row['created_at']],';');}
        fclose($out);exit;
    }

    private static function csrf():void{if(!Security::verifyCsrf($_POST['_csrf']??null)){http_response_code(419);exit('Sessione non valida');}}
}
