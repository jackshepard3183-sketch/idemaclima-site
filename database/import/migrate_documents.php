<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/scripts/bootstrap.php';

$download = in_array('--download', $argv, true);
if (in_array('--execute', $argv, true)) {
    fwrite(STDERR, "Modalita --execute disabilitata per sicurezza. Usa migrate_documents.php --download e poi import_downloaded_documents.php --execute.\n");
    exit(2);
}
$manifest = __DIR__ . '/document_migration_manifest.csv';

foreach ($argv as $arg) {
    if (!str_starts_with($arg, '--manifest=')) continue;
    $value = trim(substr($arg, strlen('--manifest=')));
    if ($value === '' || str_contains($value, "\0")) {
        fwrite(STDERR, "Valore --manifest non valido.\n");
        exit(1);
    }
    $candidate = str_starts_with($value, DIRECTORY_SEPARATOR) ? $value : __DIR__ . DIRECTORY_SEPARATOR . ltrim($value, '/\\');
    $real = realpath($candidate);
    if ($real === false || !is_file($real)) { fwrite(STDERR, "Manifest non trovato: {$value}\n"); exit(1); }
    if (!str_starts_with($value, DIRECTORY_SEPARATOR)) {
        $importRoot = realpath(__DIR__);
        if ($importRoot === false || !str_starts_with($real, $importRoot . DIRECTORY_SEPARATOR)) {
            fwrite(STDERR, "Manifest relativo fuori dalla cartella import non consentito.\n"); exit(1);
        }
    }
    $manifest = $real;
}

$targetRoot = dirname(__DIR__, 2) . '/public/uploads/documents/migrated';
$fh = fopen($manifest, 'rb');
if (!$fh) { fwrite(STDERR, "Manifest non leggibile.\n"); exit(1); }
$header = fgetcsv($fh);
if (!$header) { fwrite(STDERR, "Manifest vuoto.\n"); exit(1); }
$required = ['source_url','title','type_slug','group_label','target_filename','category_slug'];
$missingColumns = array_values(array_diff($required, $header));
if ($missingColumns !== []) { fwrite(STDERR, 'Colonne obbligatorie mancanti: ' . implode(', ', $missingColumns) . "\n"); fclose($fh); exit(1); }
$rows=[];$line=1;
while (($row=fgetcsv($fh))!==false) {
    $line++;
    if (count($row)!==count($header)) { fwrite(STDERR,"Riga {$line} non valida.\n"); fclose($fh); exit(1); }
    $rows[]=array_combine($header,$row);
}
fclose($fh);

function fetchRemotePdf(string $url): string {
    if (!preg_match('#^https://www\.idemaclima\.it/wp-content/uploads/#i',$url)) throw new RuntimeException('URL sorgente non consentito: '.$url);
    if (function_exists('curl_init')) {
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>60,CURLOPT_USERAGENT=>'IDEMA-Migration/1.1',CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS]);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
        if(!is_string($body)||$status<200||$status>=300) throw new RuntimeException('Download fallito HTTP '.$status.($err?': '.$err:''));
        return $body;
    }
    $context=stream_context_create(['http'=>['timeout'=>60,'user_agent'=>'IDEMA-Migration/1.1','follow_location'=>1,'max_redirects'=>4]]);
    $body=@file_get_contents($url,false,$context);if(!is_string($body))throw new RuntimeException('Download fallito');return $body;
}
function validatePdf(string $bytes):void { if(strlen($bytes)<5||substr($bytes,0,5)!=='%PDF-')throw new RuntimeException('Il contenuto ricevuto non è un PDF valido.'); if(strlen($bytes)>50*1024*1024)throw new RuntimeException('PDF oltre il limite di 50 MB.'); }
function localPdfByHash(string $root,string $sha):?string { if(!is_dir($root))return null;foreach(glob($root.'/*.pdf')?:[] as $path){if(is_file($path)&&hash_file('sha256',$path)===$sha)return $path;}return null; }

$stats=['manifest'=>count($rows),'downloaded'=>0,'verified'=>0,'physical_deduped'=>0,'errors'=>0];$report=[];
foreach($rows as $row){
    $item=['source_url'=>$row['source_url'],'target_filename'=>$row['target_filename'],'title'=>$row['title'],'status'=>'dry-run'];
    try{
        $requestedFilename=basename((string)$row['target_filename']);if($requestedFilename===''||!preg_match('/\.pdf$/i',$requestedFilename))throw new RuntimeException('Nome file target non valido.');
        $actualFilename=$requestedFilename;$relativePath='/uploads/documents/migrated/'.$actualFilename;$absolutePath=$targetRoot.'/'.$actualFilename;
        if($download){
            if(is_file($absolutePath)){$bytes=file_get_contents($absolutePath);if(!is_string($bytes))throw new RuntimeException('Impossibile leggere il PDF locale.');validatePdf($bytes);$item['status']='existing-local';}
            else{$bytes=fetchRemotePdf((string)$row['source_url']);validatePdf($bytes);}
            $sha=hash('sha256',$bytes);$size=strlen($bytes);$stats['verified']++;
            if(!is_file($absolutePath)){
                $same=localPdfByHash($targetRoot,$sha);
                if($same!==null){$actualFilename=basename($same);$relativePath='/uploads/documents/migrated/'.$actualFilename;$stats['physical_deduped']++;$item['status']='deduped-local';}
                else{if(!is_dir($targetRoot)&&!mkdir($targetRoot,0755,true)&&!is_dir($targetRoot))throw new RuntimeException('Impossibile creare la cartella documenti migrati.');if(file_put_contents($absolutePath,$bytes,LOCK_EX)===false)throw new RuntimeException('Scrittura PDF fallita.');@chmod($absolutePath,0644);$stats['downloaded']++;$item['status']='downloaded';}
            }
            $item['sha256']=$sha;$item['bytes']=$size;$item['file_path']=$relativePath;$item['actual_filename']=$actualFilename;
        }
    }catch(Throwable $e){$stats['errors']++;$item['status']='error';$item['error']=$e->getMessage();}
    $report[]=$item;
}
$stem=preg_replace('/[^a-z0-9._-]+/i','-',pathinfo($manifest,PATHINFO_FILENAME))?:'manifest';
$reportPath=__DIR__.'/reports/document-migration-'.$stem.'-latest.json';
file_put_contents($reportPath,json_encode(['mode'=>$download?'download':'dry-run','manifest'=>basename($manifest),'stats'=>$stats,'items'=>$report],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
echo strtoupper($download?'DOWNLOAD':'DRY-RUN')." document migration\n";foreach($stats as $k=>$v)echo str_pad($k,20).': '.$v."\n";echo 'Report: '.$reportPath."\n";
if(!$download)echo "Nessun file scaricato. Usa --download. La scrittura DB e separata e avviene solo con import_downloaded_documents.php --execute.\n";
if($stats['errors']>0)exit(2);
