<?php
declare(strict_types=1);








namespace App\Controllers\Admin;








use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use App\Services\WarrantyService;
use PDO;
use RuntimeException;
use Throwable;








final class WarrantyImportController
{
    public static function index(): void
    {
        AdminAuth::requireLogin();
        $title = 'Importazione garanzie WPForms';
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/warranty_import.php';
    }








    public static function run(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        try { $payload=self::importPayload($_FILES['manifest']??null); } catch (Throwable $e) { http_response_code(422); header('Content-Type: application/json; charset=UTF-8'); echo json_encode(['imported'=>0,'skipped'=>0,'errors'=>[$e->getMessage()]],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); return; }
        $done = 0; $skipped = 0; $errors = []; $importedItems = [];
        try {
            if (function_exists('set_time_limit')) @set_time_limit(0);
            $pdo = Database::connection();
            $columnDefinitions = $pdo->query('SHOW COLUMNS FROM warranty_registrations')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columnDefinitions as $columnDefinition) {
                if (($columnDefinition['Field'] ?? '') === 'invoice_date'
                    && strtoupper((string)($columnDefinition['Null'] ?? 'NO')) !== 'YES') {
                    $pdo->exec('ALTER TABLE warranty_registrations MODIFY invoice_date DATE NULL');
                    break;
                }
            }
            $columns = array_column($columnDefinitions, 'Field');
        } catch (Throwable $e) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['imported'=>0, 'skipped'=>0, 'errors'=>['Preparazione importazione: ' . $e->getMessage()]], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            return;
        }
        foreach ($payload['items'] as $item) {
            if (!is_array($item)) continue;
            $sourceId = (int)($item['source_id'] ?? 0);
            $invoicePath = null;
            $fgasPath = null;
            try {
                if ($sourceId < 1) throw new RuntimeException('ID WPForms mancante');
                $sourceCreatedAt = self::normalizeWpformsDate((string)($item['source_created_at'] ?? ''));
                $check = $pdo->prepare('SELECT id FROM warranty_registrations WHERE source_wpforms_id=? LIMIT 1');
                $check->execute([$sourceId]);
                if ($check->fetchColumn()) { $skipped++; continue; }
                $productType = strtolower(trim((string)($item['product_type'] ?? '')));
                $isMulti = in_array($productType, ['multi', 'multi split'], true);
                $modelCode = strtoupper(trim((string)($item['model_code'] ?? '')));
                $combination = strtoupper(trim((string)($item['combination'] ?? '')));
                if ($isMulti && str_starts_with($combination, $modelCode . ' + ')) {
                    $combination = trim(substr($combination, strlen($modelCode) + 3));
                }
                $modelId = $isMulti
                    ? WarrantyService::modelIdFromCode($pdo, $modelCode)
                    : WarrantyService::modelIdFromCombination($pdo, $combination);
                if ($modelId < 1) throw new RuntimeException('Modello principale non trovato: ' . $modelCode);
                $rule = WarrantyService::applicableRule($pdo, $modelId, (string)($item['invoice_date'] ?? ''));
                if (!$rule) throw new RuntimeException('Regola garanzia non trovata per ' . $modelCode);
                $allowIncomplete = (string)($item['import_status'] ?? '') === 'IMPORTABILE CON VERIFICA'
                    && trim((string)($item['review_warning'] ?? '')) !== '';
                $invoicePath = self::copyDocument((string)($item['invoice_url'] ?? ''), 'invoice', $sourceId, $allowIncomplete);
                $fgasPath = self::copyDocument((string)($item['fgas_url'] ?? ''), 'fgas', $sourceId, $allowIncomplete);
                $pdo->beginTransaction();
                $data = [
                    'source_wpforms_id'=>$sourceId,
                    'certificate_number'=>self::newCode($pdo),
                    'model_id'=>$modelId,
                    'warranty_rule_id'=>(int)$rule['id'],
                    'customer_first_name'=>self::normalizeName((string)($item['first_name'] ?? '')),
                    'customer_last_name'=>self::normalizeName((string)($item['last_name'] ?? '')),
                    'fiscal_code'=>strtoupper(trim((string)($item['fiscal_code'] ?? ''))),
                    'email'=>strtolower(trim((string)($item['email'] ?? ''))),
                    'phone'=>trim((string)($item['phone'] ?? '')) ?: null,
                    'address'=>trim((string)($item['address'] ?? '')),
                    'postal_code'=>strtoupper(trim((string)($item['postal_code'] ?? ''))),
                    'city'=>trim((string)($item['city'] ?? '')),
                    'province'=>Validator::provinceCode($item['province'] ?? ''),
                    'region'=>strtoupper(trim((string)($item['region'] ?? ''))),
                    'invoice_date'=>trim((string)($item['invoice_date'] ?? '')) ?: null,
                    'invoice_file'=>$invoicePath,
                    'fgas_file'=>$fgasPath,
                    'privacy_accepted_at'=>$sourceCreatedAt,
                    'status'=>'pending',
                    'warranty_years'=>(int)$rule['warranty_years'],
                    'extension_formula'=>$rule['extension_formula'],
                    'registration_days_limit'=>$rule['registration_days_limit'],
                    'admin_notes'=>null,
                    'reviewed_at'=>null,
                    'invoice_required_snapshot'=>(int)$rule['invoice_required'],
                    'fgas_required_snapshot'=>(int)$rule['fgas_required'],
                    'import_review_warning'=>trim((string)($item['review_warning'] ?? '')) ?: null,
                    'imported_at'=>date('Y-m-d H:i:s'),
                    'created_at'=>$sourceCreatedAt,
                ];
                $data = array_intersect_key($data, array_flip($columns));
                $names = array_keys($data);
                $sql = 'INSERT INTO warranty_registrations (' . implode(',', $names) . ') VALUES (' . implode(',', array_fill(0,count($names),'?')) . ')';
                $pdo->prepare($sql)->execute(array_values($data));
                $registrationId = (int)$pdo->lastInsertId();
                $unit = $pdo->prepare('INSERT INTO warranty_units (registration_id,model_id,unit_type,serial_number) VALUES (?,?,?,?)');
                $outdoorSerial = strtoupper(trim((string)($item['outdoor_serial'] ?? '')));
                if ($outdoorSerial !== '') $unit->execute([$registrationId,$modelId,'outdoor',$outdoorSerial]);
                foreach (($item['indoor_serials'] ?? []) as $serial) {
                    $serial = strtoupper(trim((string)$serial));
                    if ($serial !== '') $unit->execute([$registrationId,null,'indoor',$serial]);
                }
                $pdo->prepare('INSERT INTO warranty_registration_details (registration_id,product_type,outer_unit,combination) VALUES (?,?,?,?)')
                    ->execute([$registrationId,$isMulti ? 'multi' : 'mono',$modelCode,$combination]);
                $pdo->commit();
                $done++;
                $importedItems[]=['id'=>$sourceId,'nome'=>trim((string)($item['first_name']??'').' '.(string)($item['last_name']??'')),'email'=>strtolower(trim((string)($item['email']??''))),'modello'=>$modelCode];
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                self::removeImportedDocument($invoicePath);
                self::removeImportedDocument($fgasPath);
                $errors[] = '#' . $sourceId . ': ' . $e->getMessage();
            }
        }
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['imported'=>$done,'skipped'=>$skipped,'imported_items'=>$importedItems,'errors'=>$errors], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
    }


    private static function importPayload(mixed $file): array
    {
        if(!is_array($file)||!is_uploaded_file((string)($file['tmp_name']??''))) throw new RuntimeException('Seleziona il file XLSX esportato da WPForms oppure il manifest JSON.');
        if((int)($file['size']??0)>15728640) throw new RuntimeException('Il file supera 15 MB.');
        $name=strtolower((string)($file['name']??''));
        if(str_ends_with($name,'.xlsx')) return ['items'=>self::xlsxItems((string)$file['tmp_name'])];
        $payload=json_decode((string)file_get_contents((string)$file['tmp_name']),true);
        if(!is_array($payload)||!is_array($payload['items']??null)) throw new RuntimeException('File non valido: usa un XLSX WPForms o un manifest JSON compatibile.');
        return $payload;
    }

    private static function xlsxItems(string $path): array
    {
        if(!class_exists('ZipArchive')) throw new RuntimeException('La lettura XLSX non è disponibile sul server.');
        $zip=new \ZipArchive(); if($zip->open($path)!==true) throw new RuntimeException('File XLSX non valido o danneggiato.');
        try {
            $shared=[];$sharedRaw=$zip->getFromName('xl/sharedStrings.xml');
            if(is_string($sharedRaw)){ $sx=simplexml_load_string($sharedRaw,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA); if($sx){$sxNs=$sx->getNamespaces(true);$sxRoot=$sx->children($sxNs['']??'http://schemas.openxmlformats.org/spreadsheetml/2006/main');foreach($sxRoot->si as $si){$parts=[];foreach($si->xpath('.//*[local-name()="t"]')?:[] as $t)$parts[]=(string)$t;$shared[]=implode('',$parts);}} }
            $sheetRaw=$zip->getFromName('xl/worksheets/sheet1.xml'); if(!is_string($sheetRaw))throw new RuntimeException('Il foglio principale non è presente nel file XLSX.');
            $sheet=simplexml_load_string($sheetRaw,'SimpleXMLElement',LIBXML_NONET|LIBXML_NOCDATA); if(!$sheet)throw new RuntimeException('Il foglio XLSX non può essere letto.');
            $sheetNs=$sheet->getNamespaces(true);$sheetUri=$sheetNs['']??'http://schemas.openxmlformats.org/spreadsheetml/2006/main';$sheetRoot=$sheet->children($sheetUri);
            $matrix=[];foreach($sheetRoot->sheetData->row as $row){$values=[];foreach($row->c as $cell){$ref=(string)$cell['r'];preg_match('/^[A-Z]+/',$ref,$m);$index=self::columnIndex($m[0]??'A');$type=(string)$cell['t'];$nodes=$cell->children($sheetUri);if($type==='inlineStr'){$parts=[];foreach($nodes->is->xpath('.//*[local-name()="t"]')?:[] as $t)$parts[]=(string)$t;$value=implode('',$parts);}else{$value=(string)$nodes->v;if($type==='s')$value=(string)($shared[(int)$value]??'');}$values[$index]=trim($value);}if($values!==[]){ksort($values);$matrix[]=$values;}}
        } finally {$zip->close();}
        if(count($matrix)<2)throw new RuntimeException('Il file XLSX non contiene registrazioni.');
        $headers=[];foreach($matrix[0] as $i=>$label)$headers[$i]=self::headerKey((string)$label);$items=[];
        foreach(array_slice($matrix,1) as $row){$record=[];foreach($headers as $i=>$key)if($key!=='')$record[$key]=(string)($row[$i]??'');if(count(array_filter($record,static fn($v)=>trim((string)$v)!==''))===0)continue;$sourceId=(int)self::pick($record,['entry id','id iscrizione','id','numero']);$created=self::pick($record,['date','data','data creazione','created at','data invio']);$invoiceDate=self::pick($record,['data fattura','invoice date','data di acquisto']);$indoor=[];foreach($record as $key=>$value)if($value!==''&&(str_contains($key,'matricola')||str_contains($key,'serial'))&&(str_contains($key,'interna')||str_contains($key,'indoor')))$indoor[]=$value;$required=['nome'=>self::pick($record,['nome','first name']),'cognome'=>self::pick($record,['cognome','last name']),'email'=>self::pick($record,['email','e-mail']),'modello'=>self::pick($record,['modello','model code','unita esterna','unità esterna'])];$missing=[];foreach($required as $label=>$value)if(trim($value)==='')$missing[]=$label;$items[]=['source_id'=>$sourceId,'source_created_at'=>self::xlsxDate($created),'first_name'=>$required['nome'],'last_name'=>$required['cognome'],'fiscal_code'=>self::pick($record,['codice fiscale','cf','fiscal code']),'email'=>$required['email'],'phone'=>self::pick($record,['telefono','recapito telefonico','phone']),'address'=>self::pick($record,['indirizzo','address']),'postal_code'=>self::pick($record,['cap','postal code']),'city'=>self::pick($record,['citta','città','city']),'province'=>self::pick($record,['provincia','province']),'region'=>self::pick($record,['regione','region']),'invoice_date'=>self::xlsxDate($invoiceDate,true),'invoice_url'=>self::pick($record,['fattura','allega fattura','invoice','file fattura']),'fgas_url'=>self::pick($record,['f-gas','fgas','certificato f-gas','file f-gas']),'product_type'=>self::pick($record,['tipologia','tipo prodotto','product type']),'model_code'=>$required['modello'],'combination'=>self::pick($record,['combinazione','combination','configurazione']),'outdoor_serial'=>self::pick($record,['matricola unita esterna','matricola unità esterna','outdoor serial','numero seriale esterna']),'indoor_serials'=>$indoor,'import_status'=>$missing===[]?'IMPORTABILE':'IMPORTABILE CON VERIFICA','review_warning'=>$missing===[]?'':'Campi XLSX da verificare: '.implode(', ',$missing)];}
        if($items===[])throw new RuntimeException('Nessuna registrazione riconosciuta nel file XLSX.');return $items;
    }

    private static function columnIndex(string $letters): int {$value=0;foreach(str_split($letters) as $letter)$value=$value*26+(ord($letter)-64);return max(0,$value-1);}
    private static function headerKey(string $value): string {$value=html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8');$value=mb_strtolower(trim($value),'UTF-8');$value=strtr($value,['à'=>'a','á'=>'a','è'=>'e','é'=>'e','ì'=>'i','ò'=>'o','ù'=>'u']);return trim(preg_replace('/\s+/u',' ',preg_replace('/[^a-z0-9@._ -]+/u',' ',$value)??$value)??$value);}
    private static function pick(array $record,array $aliases): string {foreach($aliases as $alias){$key=self::headerKey($alias);if(isset($record[$key])&&trim((string)$record[$key])!=='')return trim((string)$record[$key]);}foreach($aliases as $alias){$key=self::headerKey($alias);foreach($record as $header=>$value)if($value!==''&&(str_contains($header,$key)||str_contains($key,$header)))return trim((string)$value);}return '';}
    private static function xlsxDate(string $value,bool $dateOnly=false): string {$value=trim($value);if($value==='')return '';if(is_numeric($value)){$seconds=((float)$value-25569)*86400;return gmdate($dateOnly?'Y-m-d':'Y-m-d H:i:s',(int)round($seconds));}$timestamp=strtotime(str_replace('/','-',$value));if($timestamp!==false)return date($dateOnly?'Y-m-d':'Y-m-d H:i:s',$timestamp);return $value;}

    private static function copyDocument(string $url, string $kind, int $sourceId, bool $allowMissing = false): ?string
    {
        $url = trim($url);
        if ($url === '') {
            if ($allowMissing) return null;
            throw new RuntimeException('Documento ' . $kind . ' mancante');
        }
        if (!preg_match('#^https?://www\.idemaclima\.it/wp-content/uploads/wpforms/#i', $url)) throw new RuntimeException('URL documento non consentito');
        $url = preg_replace('#^http://#i', 'https://', $url) ?? $url;
        $ext = strtolower((string)pathinfo((string)parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) throw new RuntimeException('Formato ' . $kind . ' non valido');
        if (!function_exists('curl_init')) throw new RuntimeException('cURL non disponibile sul server');

        $name = 'wpforms-' . $sourceId . '-' . $kind . '-' . bin2hex(random_bytes(8)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        $relative = 'warranty/imported/' . $name;
        $absolute = dirname(__DIR__, 3) . '/storage/private/' . $relative;
        if (!is_dir(dirname($absolute)) && !mkdir(dirname($absolute), 0700, true) && !is_dir(dirname($absolute))) throw new RuntimeException('Cartella privata non disponibile');

        $temporary = $absolute . '.part-' . bin2hex(random_bytes(4));
        $handle = fopen($temporary, 'wb');
        if ($handle === false) throw new RuntimeException('File temporaneo ' . $kind . ' non disponibile');

        $tooLarge = false;
        $ch = curl_init($url);
        if ($ch === false) {
            fclose($handle);
            @unlink($temporary);
            throw new RuntimeException('Inizializzazione download ' . $kind . ' non riuscita');
        }
        curl_setopt_array($ch, [
            CURLOPT_FILE => $handle,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; IDEMA-WPForms-Migration/1.0)',
            CURLOPT_HTTPHEADER => ['Accept: application/pdf,image/jpeg,image/png,*/*'],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_XFERINFOFUNCTION => static function ($curl, $downloadTotal, $downloaded) use (&$tooLarge): int {
                if ($downloadTotal > 15728640 || $downloaded > 15728640) {
                    $tooLarge = true;
                    return 1;
                }
                return 0;
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($handle);

        if ($ok !== true || $status < 200 || $status >= 300) {
            @unlink($temporary);
            if ($tooLarge) throw new RuntimeException('Documento ' . $kind . ' superiore a 15 MB');
            $detail = $error !== '' ? ': ' . $error : ' (HTTP ' . $status . ')';
            throw new RuntimeException('Download ' . $kind . ' non riuscito' . $detail);
        }

        $size = filesize($temporary);
        if ($size === false || $size < 5 || $size > 15728640) {
            @unlink($temporary);
            throw new RuntimeException('Download ' . $kind . ' non riuscito');
        }
        if ($ext === 'pdf' && file_get_contents($temporary, false, null, 0, 5) !== '%PDF-') {
            @unlink($temporary);
            throw new RuntimeException('PDF ' . $kind . ' non valido');
        }
        if (!rename($temporary, $absolute)) {
            @unlink($temporary);
            throw new RuntimeException('Salvataggio ' . $kind . ' non riuscito');
        }
        @chmod($absolute, 0600);
        return $relative;
    }



    private static function normalizeExistingImportedNames(PDO $pdo): void
    {
        $rows = $pdo->query(
            'SELECT id, customer_first_name, customer_last_name, province
             FROM warranty_registrations
             WHERE source_wpforms_id IS NOT NULL'
        )->fetchAll(PDO::FETCH_ASSOC);

        $update = $pdo->prepare(
            'UPDATE warranty_registrations
             SET customer_first_name=?, customer_last_name=?, province=?
             WHERE id=?'
        );

        foreach ($rows as $row) {
            $firstName = self::normalizeName((string)($row['customer_first_name'] ?? ''));
            $lastName = self::normalizeName((string)($row['customer_last_name'] ?? ''));
            $province = Validator::provinceCode($row['province'] ?? '');
            if ($firstName === (string)$row['customer_first_name']
                && $lastName === (string)$row['customer_last_name']
                && $province === (string)$row['province']) {
                continue;
            }
            $update->execute([$firstName, $lastName, $province, (int)$row['id']]);
        }
    }

    private static function normalizeName(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        if ($value === '') return '';

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);
    }

    private static function normalizeWpformsDate(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) return $value;
        if (!preg_match('/^(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\s+(\d{1,2}):(\d{2})$/u', $value, $m)) {
            throw new RuntimeException('Data WPForms non valida: ' . $value);
        }
        $months = [
            'gennaio'=>1,'febbraio'=>2,'marzo'=>3,'aprile'=>4,'maggio'=>5,'giugno'=>6,
            'luglio'=>7,'agosto'=>8,'settembre'=>9,'ottobre'=>10,'novembre'=>11,'dicembre'=>12,
        ];
        $month = $months[strtolower($m[2])] ?? 0;
        $day = (int)$m[1]; $year = (int)$m[3]; $hour = (int)$m[4]; $minute = (int)$m[5];
        if ($month < 1 || !checkdate($month, $day, $year) || $hour > 23 || $minute > 59) {
            throw new RuntimeException('Data WPForms non valida: ' . $value);
        }
        return sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute);
    }


    private static function cleanupOrphanedImports(PDO $pdo): void
    {
        $used = [];
        $rows = $pdo->query('SELECT invoice_file, fgas_file FROM warranty_registrations')->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            foreach (['invoice_file','fgas_file'] as $field) {
                $path = trim((string)($row[$field] ?? ''));
                if ($path !== '') $used[$path] = true;
            }
        }
        $directory = dirname(__DIR__, 3) . '/storage/private/warranty/imported';
        foreach (glob($directory . '/wpforms-*') ?: [] as $file) {
            $relative = 'warranty/imported/' . basename($file);
            if (is_file($file) && !isset($used[$relative])) @unlink($file);
        }
    }


    private static function removeImportedDocument(?string $relative): void
    {
        if ($relative === null || !str_starts_with($relative, 'warranty/imported/wpforms-')) return;
        $absolute = dirname(__DIR__, 3) . '/storage/private/' . $relative;
        if (is_file($absolute)) @unlink($absolute);
    }

    private static function newCode(PDO $pdo): string
    {
        $alphabet='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code=''; for($i=0;$i<8;$i++) $code.=$alphabet[random_int(0,strlen($alphabet)-1)];
            $number='IDM-'.$code;
            $q=$pdo->prepare('SELECT 1 FROM warranty_registrations WHERE certificate_number=? LIMIT 1'); $q->execute([$number]);
        } while ($q->fetchColumn());
        return $number;
    }
}