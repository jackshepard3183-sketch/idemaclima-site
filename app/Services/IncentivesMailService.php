<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class IncentivesMailService
{
    public static function notify(array $request):void
    {
        $settings=self::settings();if(!self::enabled($settings))return;
        $raw=(string)($settings['incentives_recipients']??'');$recipients=preg_split('/[;,\s]+/',trim($raw))?:[];
        $recipients=array_values(array_unique(array_filter($recipients,static fn(string $email):bool=>(bool)filter_var($email,FILTER_VALIDATE_EMAIL))));
        if(!$recipients)return;
        $body="È stata ricevuta una nuova richiesta EasyTool.\n\n";
        foreach(['Nome'=>trim((string)($request['first_name']??'')).' '.trim((string)($request['last_name']??'')),'Azienda'=>$request['company']??'','Località'=>trim((string)($request['postal_code']??'')).' '.trim((string)($request['city']??'')).' ('.trim((string)($request['province']??'')).')','Regione'=>$request['region']??'','Telefono'=>$request['phone']??'','Email'=>$request['email']??'','Profilo'=>$request['role']??''] as $label=>$value){$value=self::clean((string)$value);if($value!=='')$body.=$label.': '.$value."\n";}
        $body.="\nAccedi al pannello IDEMA per gestire la richiesta.\n";
        $from=filter_var($settings['sender_email']??'',FILTER_VALIDATE_EMAIL)?$settings['sender_email']:'no-reply@rappresentanzeguanzirolisas.it';$name=self::clean((string)($settings['sender_name']??'Idema Clima Srl'));if(in_array(strtolower($name),['idema clima','idema sito web'],true))$name='Idema Clima Srl';
        $headers=['From: '.$name.' <'.$from.'>','Content-Type: text/plain; charset=UTF-8','X-Mailer: IDEMA Website'];$reply=(string)($request['email']??'');if(filter_var($reply,FILTER_VALIDATE_EMAIL))$headers[]='Reply-To: '.$reply;
        $subject='Nuova richiesta EasyTool';$encoded=function_exists('mb_encode_mimeheader')?mb_encode_mimeheader($subject,'UTF-8'):$subject;
        foreach($recipients as $recipient)if(!@mail($recipient,$encoded,$body,implode("\r\n",$headers)))error_log('IDEMA EasyTool email not sent');
    }
    private static function settings():array{try{$s=Database::connection()->query("SELECT setting_key,setting_value FROM site_settings WHERE setting_group='email'");return $s->fetchAll(PDO::FETCH_KEY_PAIR)?:[];}catch(\Throwable){return [];}}
    private static function enabled(array $settings):bool{return !in_array(strtolower(trim((string)($settings['notifications_enabled']??'1'))),['0','false','no','off'],true);}
    private static function clean(string $value):string{return trim((string)preg_replace('/[\r\n]+/',' ',$value));}
}
