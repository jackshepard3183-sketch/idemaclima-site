<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$checks=[];$blocking=0;
function checkItem(array &$checks,int &$blocking,string $name,bool $ok,string $detail,bool $required=true):void{$checks[]=['name'=>$name,'ok'=>$ok,'required'=>$required,'detail'=>$detail];if($required&&!$ok)$blocking++;}
function envValue(string $name):string{$v=getenv($name);if($v!==false)return trim((string)$v);return '';}

$envFile=$root.'/.env';
if(is_file($envFile)){foreach(file($envFile,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;[$name,$value]=explode('=',$line,2);$name=trim($name);$value=trim($value," \t\n\r\0\x0B\"'");if(getenv($name)===false)putenv($name.'='.$value);}}

checkItem($checks,$blocking,'PHP >= 8.2',version_compare(PHP_VERSION,'8.2.0','>='),'Versione: '.PHP_VERSION);
foreach(['pdo','pdo_mysql','fileinfo','json','openssl','mbstring'] as $ext)checkItem($checks,$blocking,'Estensione '.$ext,extension_loaded($ext),extension_loaded($ext)?'disponibile':'mancante');
checkItem($checks,$blocking,'Estensione curl',extension_loaded('curl'),extension_loaded('curl')?'disponibile':'mancante; fallback stream disponibile',false);

$appEnv=envValue('APP_ENV');$appUrl=rtrim(envValue('APP_URL'),'/');$appKey=envValue('APP_KEY');$timezone=envValue('APP_TIMEZONE')?:'Europe/Rome';$basePath=envValue('APP_BASE_PATH');
$basePath=$basePath===''||$basePath==='/'?'':'/'.trim($basePath,'/');
checkItem($checks,$blocking,'APP_ENV',in_array($appEnv,['staging','production'],true),'Valore: '.($appEnv?:'(vuoto)'));
checkItem($checks,$blocking,'APP_URL HTTPS',$appUrl!==''&&filter_var($appUrl,FILTER_VALIDATE_URL)!==false&&str_starts_with(strtolower($appUrl),'https://'),'Valore: '.($appUrl?:'(vuoto)'));
checkItem($checks,$blocking,'APP_BASE_PATH formato',$basePath===''||preg_match('#^/[A-Za-z0-9._~/-]+$#',$basePath)===1,'Valore: '.($basePath?:'(root)'));
$baseConsistent=$basePath===''||str_ends_with($appUrl,$basePath);
checkItem($checks,$blocking,'APP_URL coerente con APP_BASE_PATH',$baseConsistent,$baseConsistent?'coerente':'APP_URL deve terminare con '.$basePath);
checkItem($checks,$blocking,'APP_KEY',strlen($appKey)>=32,'Lunghezza: '.strlen($appKey));
checkItem($checks,$blocking,'APP_TIMEZONE',in_array($timezone,timezone_identifiers_list(),true),'Valore: '.$timezone);
foreach(['DB_HOST','DB_DATABASE','DB_USERNAME','DB_PASSWORD'] as $name){$value=envValue($name);checkItem($checks,$blocking,$name,$value!=='',$value!==''?'configurato':'mancante');}
$trusted=envValue('TRUSTED_PROXIES');checkItem($checks,$blocking,'TRUSTED_PROXIES',$trusted!==''||$appEnv==='staging',$trusted!==''?'configurato':'vuoto; accettabile solo se staging non è dietro proxy',false);

$paths=[
    'storage/private'=>$root.'/storage/private',
    'public/uploads'=>$root.'/public/uploads',
    'database/import/reports'=>$root.'/database/import/reports',
];
foreach($paths as $label=>$path){$exists=is_dir($path);$writable=$exists&&is_writable($path);checkItem($checks,$blocking,$label,$exists&&$writable,($exists?'esiste':'manca').'; '.($writable?'scrivibile':'non scrivibile'));}

$migrationDir=$root.'/database/migrations';$migrations=glob($migrationDir.'/*.sql')?:[];sort($migrations,SORT_NATURAL);checkItem($checks,$blocking,'Migration SQL',count($migrations)>=22,'Trovate: '.count($migrations));
$numbers=[];foreach($migrations as $path){if(preg_match('/\/(\d{3})_/',str_replace('\\','/',$path),$m))$numbers[]=(int)$m[1];}$duplicates=array_diff_assoc($numbers,array_unique($numbers));checkItem($checks,$blocking,'Numerazione migration univoca',$duplicates===[],$duplicates===[]?'nessun duplicato':'duplicati: '.implode(', ',array_unique($duplicates)));
checkItem($checks,$blocking,'Migration runner',is_file($root.'/scripts/migrate.php'),is_file($root.'/scripts/migrate.php')?'presente':'mancante');

$requiredFiles=[
    'database/import/preflight_historical_products.php',
    'database/import/import_historical_products.php',
    'database/import/migrate_documents.php',
    'database/import/import_downloaded_documents.php',
    'database/import/import_catalogs.php',
    'tests/run_all.sh',
];
foreach($requiredFiles as $file)checkItem($checks,$blocking,$file,is_file($root.'/'.$file),is_file($root.'/'.$file)?'presente':'mancante');

$dbOk=false;$dbDetail='non verificato';$dbCompat=false;$dbCompatDetail='non verificata';
if(envValue('DB_HOST')!==''&&envValue('DB_DATABASE')!==''&&envValue('DB_USERNAME')!==''&&extension_loaded('pdo_mysql')){
    try{
        $port=(int)(envValue('DB_PORT')?:3306);$dsn='mysql:host='.envValue('DB_HOST').';port='.$port.';dbname='.envValue('DB_DATABASE').';charset=utf8mb4';
        $pdo=new PDO($dsn,envValue('DB_USERNAME'),envValue('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
        $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();$dbOk=true;$dbDetail='connessione OK; server '.$version;
        $isMaria=stripos($version,'mariadb')!==false;
        preg_match('/(\d+\.\d+\.\d+)/',$version,$m);$numeric=$m[1]??'0.0.0';
        $dbCompat=$isMaria?version_compare($numeric,'10.2.1','>='):version_compare($numeric,'8.0.16','>=');
        $dbCompatDetail=($isMaria?'MariaDB ':'MySQL ').$numeric.($dbCompat?' compatibile con CHECK vincolanti':' troppo vecchio per i CHECK vincolanti usati dalle migration');
    }catch(Throwable $e){$dbDetail='connessione fallita: '.$e->getMessage();}
}
checkItem($checks,$blocking,'Connessione database',$dbOk,$dbDetail);
checkItem($checks,$blocking,'Compatibilità database migration',$dbCompat,$dbCompatDetail);

$report=['ok'=>$blocking===0,'blocking_failures'=>$blocking,'generated_at'=>date(DATE_ATOM),'checks'=>$checks];
$reportDir=$root.'/database/import/reports';if(is_dir($reportDir)&&is_writable($reportDir))@file_put_contents($reportDir.'/staging-readiness-latest.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo "IDEMA staging readiness\n";foreach($checks as $c)echo ($c['ok']?'[OK] ':'[!!] ').$c['name'].' - '.$c['detail'].($c['required']?'':' (warning)')."\n";echo "Blocking failures: {$blocking}\n";exit($blocking===0?0:2);
