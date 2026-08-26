<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use PDO;

final class AnalyticsController
{
    public static function documentDownload(string $id): void
    {
        $pdo=Database::connection();
        $s=$pdo->prepare('SELECT d.id,d.file_path,COALESCE(a.pdf_tracking_enabled,1) tracking FROM documents d CROSS JOIN analytics_settings a WHERE d.id=? AND d.published=1 AND a.id=1 LIMIT 1');
        $s->execute([(int)$id]);$doc=$s->fetch(PDO::FETCH_ASSOC);
        if(!$doc){http_response_code(404);exit('Documento non trovato');}
        if((int)$doc['tracking']===1){
            $ip=(string)($_SERVER['REMOTE_ADDR']??'');$ua=(string)($_SERVER['HTTP_USER_AGENT']??'');
            $v=$ip!==''?hash('sha256',$ip):null;$u=$ua!==''?hash('sha256',$ua):null;
            $i=$pdo->prepare("INSERT INTO document_events(document_id,event_type,source_path,visitor_hash,user_agent_hash) VALUES(?,'download',?,?,?)");
            $i->execute([(int)$doc['id'],(string)($_SERVER['HTTP_REFERER']??''),$v,$u]);
        }
        $path=(string)$doc['file_path'];
        if(preg_match('#^https?://#i',$path)){header('Location: '.$path, true, 302);exit;}
        $local=dirname(__DIR__,3).'/public'.$path;
        if(!str_starts_with($path,'/uploads/')||!is_file($local)){header('Location: '.$path, true, 302);exit;}
        header('Content-Type: application/pdf');header('Content-Disposition: inline; filename="'.basename($local).'"');header('X-Content-Type-Options: nosniff');readfile($local);exit;
    }
}
