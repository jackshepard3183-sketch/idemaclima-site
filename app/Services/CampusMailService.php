<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class CampusMailService
{
    public static function notifyInternal(string $subject,array $rows,?string $replyTo=null):bool
    {
        if(!self::enabled())return true;$body="È stata ricevuta una nuova richiesta Campus IDEMA.\n\n";
        foreach($rows as $label=>$value){$value=trim((string)$value);if($value!=='')$body.=self::clean((string)$label).': '.$value."\n";}
        $body.="\nAccedi al pannello amministrativo IDEMA per gestire la richiesta.\n";
        $ok=true;foreach(self::recipients() as $recipient)$ok=self::send($recipient,$subject,$body,$replyTo)&&$ok;return $ok;
    }

    public static function notifyParticipant(string $email,string $subject,string $body):bool
    {
        return self::enabled()&&filter_var($email,FILTER_VALIDATE_EMAIL)?self::send($email,$subject,$body):true;
    }

    private static function send(string $recipient,string $subject,string $body,?string $replyTo=null):bool
    {
        $settings=self::settings();$from=filter_var($settings['sender_email']??'',FILTER_VALIDATE_EMAIL)?$settings['sender_email']:'no-reply@rappresentanzeguanzirolisas.it';$name=self::clean($settings['sender_name']??'Idema Clima Srl');if(in_array(strtolower($name),['idema clima','idema sito web'],true))$name='Idema Clima Srl';
        $headers=['From: '.$name.' <'.$from.'>','Content-Type: text/plain; charset=UTF-8','X-Mailer: IDEMA Website'];
        if($replyTo&&filter_var($replyTo,FILTER_VALIDATE_EMAIL))$headers[]='Reply-To: '.$replyTo;
        $subject=self::clean($subject);$encoded=function_exists('mb_encode_mimeheader')?mb_encode_mimeheader($subject,'UTF-8'):$subject;
        $sent=@mail($recipient,$encoded,$body,implode("\r\n",$headers));if(!$sent)error_log('IDEMA Campus email not sent: '.$subject);return $sent;
    }

    private static function recipients():array
    {
        $raw=self::settings()['campus_recipients']??'';$values=preg_split('/[;,\s]+/',trim($raw))?:[];$valid=array_values(array_unique(array_filter($values,static fn(string $v):bool=>(bool)filter_var($v,FILTER_VALIDATE_EMAIL))));
        return $valid?:['commerciale.tre@idemaclima.it'];
    }

    private static function enabled():bool{$v=self::settings()['notifications_enabled']??'1';return !in_array(strtolower(trim($v)),['0','false','no','off'],true);}
    private static function settings():array
    {
        static $values=null;if($values!==null)return $values;$values=[];
        try{$stmt=Database::connection()->query("SELECT setting_key,setting_value FROM site_settings WHERE setting_group='email'");$values=$stmt->fetchAll(PDO::FETCH_KEY_PAIR)?:[];}catch(\Throwable){$values=[];}
        return $values;
    }
    private static function clean(string $value):string{return trim((string)preg_replace('/[\r\n]+/',' ',$value));}
}
