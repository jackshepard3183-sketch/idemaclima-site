<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\Security;
use PDO;

final class AnalyticsController
{
    public static function documentDownload(string $id): void
    {
        $pdo=Database::connection();
        $s=$pdo->prepare('SELECT d.id,d.file_path,COALESCE(a.pdf_tracking_enabled,1) tracking,COALESCE(a.internal_tracking_retention_days,180) retention_days FROM documents d CROSS JOIN analytics_settings a WHERE d.id=? AND d.published=1 AND a.id=1 LIMIT 1');
        $s->execute([(int)$id]);$doc=$s->fetch(PDO::FETCH_ASSOC);
        if(!$doc){http_response_code(404);exit('Documento non trovato');}

        if((int)$doc['tracking']===1){
            self::recordDownload($pdo,(int)$doc['id']);
            self::maybePrune($pdo,(int)$doc['retention_days']);
        }

        $path=(string)$doc['file_path'];
        if(preg_match('#^https?://#i',$path)){header('Location: '.$path, true, 302);exit;}
        $local=dirname(__DIR__,3).'/public'.$path;
        if(!str_starts_with($path,'/uploads/')||!is_file($local)){header('Location: '.$path, true, 302);exit;}
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($local) ?: 'application/octet-stream';
        header('Content-Type: '.$mime);
        header('Content-Disposition: inline; filename="'.self::safeFilename(basename($local)).'"');
        header('Content-Length: '.filesize($local));
        header('X-Content-Type-Options: nosniff');
        readfile($local);exit;
    }

    private static function recordDownload(PDO $pdo,int $documentId):void
    {
        $ip=Security::clientIp();
        $key=(string)($_ENV['APP_KEY']??getenv('APP_KEY')?:'');
        $visitor=null;
        if($ip!=='' && $key!==''){
            $visitor=hash_hmac('sha256',date('Y-m-d').'|'.$ip,$key);
        }
        $source=self::referrerPath((string)($_SERVER['HTTP_REFERER']??''));
        $i=$pdo->prepare("INSERT INTO document_events(document_id,event_type,source_path,visitor_hash,user_agent_hash) VALUES(?,'download',?,?,NULL)");
        $i->execute([$documentId,$source,$visitor]);
    }

    private static function referrerPath(string $referrer):?string
    {
        if($referrer==='')return null;
        $parts=parse_url($referrer);
        if(!is_array($parts))return null;
        $path=(string)($parts['path']??'');
        if($path==='')return null;
        return substr($path,0,255);
    }

    private static function maybePrune(PDO $pdo,int $days):void
    {
        $days=max(1,min(3650,$days));
        if(random_int(1,100)!==1)return;
        $pdo->exec('DELETE FROM document_events WHERE created_at < DATE_SUB(NOW(), INTERVAL '.$days.' DAY)');
    }

    private static function safeFilename(string $name):string
    {
        $name=preg_replace('/[^A-Za-z0-9._-]+/','-',basename($name))?:'documento';
        return substr($name,0,180);
    }
}
