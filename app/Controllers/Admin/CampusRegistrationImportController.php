<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;
use App\Core\Validator;
use PDO;
use RuntimeException;
use Throwable;

final class CampusRegistrationImportController
{
    private const EVENT_SLUGS = [
        '10764' => 'pdc-idronici-residenziali-acs-pre-vendita-14-11-2025',
        '10787' => 'evento-agenzie-commerciali-vrf-03-12-2025',
        '10832' => 'sistemi-pdc-idronici-prato-16-12-2025',
        '10815' => 'sistemi-pdc-idronici-vertemate-27-01-2026',
        '10962' => 'sistemi-pdc-idronici-vertemate-30-04-2026',
        '11154' => 'r290-prodotti-sicurezza-limiti-operativi-r32-r410a-08-10-2026',
        '11190' => 'r290-prodotti-sicurezza-e-limiti-operativi-confronto-con-r32-e-r410a',
    ];

    public static function index(): void
    {
        AdminAuth::requireLogin();
        $title = 'Importazione iscrizioni Campus';
        $user = AdminAuth::user();
        $csrf = Security::csrfToken();
        require dirname(__DIR__, 2) . '/Views/admin/campus_registration_import.php';
    }

    public static function run(): void
    {
        AdminAuth::requireLogin();
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) { http_response_code(419); exit('Sessione non valida'); }
        $execute = ($_POST['mode'] ?? '') === 'execute';
        $selectedRaw=(array)($_POST['selected_ids']??[]); $selectedCsv=trim((string)($_POST['selected_ids_csv']??'')); if($selectedCsv!=='')$selectedRaw=array_merge($selectedRaw,explode(',',$selectedCsv)); $selectedIds=array_values(array_unique(array_filter(array_map('intval',$selectedRaw),static fn($id)=>$id>0))); 
        if($execute&&$selectedIds===[]){http_response_code(422);self::respond(['mode'=>'execute','validated'=>0,'imported'=>0,'skipped'=>0,'to_import'=>[],'errors'=>['Seleziona almeno un’iscrizione da importare.']]);return;}
        try {
            $payload = self::payload();
            $rows = $payload['registrations'];
            self::validatePayload($payload, $rows);
            $pdo = Database::connection();
            if ($execute) $pdo->beginTransaction();
            try {
                $eventIds = self::eventIds($pdo, $execute);
                $result = self::process($pdo, $rows, $eventIds, $execute, $selectedIds);
                if ($execute) { self::verifySelected($pdo, $selectedIds); $pdo->commit(); }
            } catch (Throwable $e) {
                if ($execute && $pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            self::respond($result + ['mode' => $execute ? 'execute' : 'check', 'notifications_sent' => 0]);
        } catch (Throwable $e) {
            http_response_code(422);
            self::respond(['mode' => $execute ? 'execute' : 'check', 'validated' => 0, 'imported' => 0, 'skipped' => 0, 'notifications_sent' => 0, 'errors' => [$e->getMessage()]]);
        }
    }

    private static function payload(): array
    {
        $file = $_FILES['manifest'] ?? null;
        if (!is_array($file) || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) throw new RuntimeException('Seleziona un file XML WordPress oppure un manifest JSON.');
        if ((int)($file['size'] ?? 0) > 5242880) throw new RuntimeException('Il file supera 5 MB.');
        $raw = (string) file_get_contents((string)$file['tmp_name']);
        $first = ltrim($raw)[0] ?? '';
        if ($first === '<') return self::xmlPayload($raw);
        $payload = json_decode($raw, true);
        if (!is_array($payload) || !is_array($payload['registrations'] ?? null)) throw new RuntimeException('File non valido: carica un XML WordPress o un manifest JSON compatibile.');
        return $payload;
    }

    private static function xmlPayload(string $raw): array
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET | LIBXML_NOCDATA);
            if ($xml === false) throw new RuntimeException('XML WordPress non valido o danneggiato.');
            $namespaces = $xml->getDocNamespaces(true);
            $wpUri = (string)($namespaces['wp'] ?? 'http://wordpress.org/export/1.2/');
            $rows = [];
            foreach ($xml->channel->item as $item) {
                $wp = $item->children($wpUri);
                if (trim((string)$wp->post_type) !== 'event_registration') continue;
                $meta = [];
                foreach ($wp->postmeta as $entry) {
                    $key = trim((string)$entry->meta_key);
                    if ($key !== '') $meta[$key] = trim((string)$entry->meta_value);
                }
                $sourceId = (int)$wp->post_id;
                if ($sourceId < 1) continue;
                $attendee = trim((string)($meta['_attendee_name'] ?? ''));
                $firstName = trim((string)($meta['_Nome'] ?? ''));
                $lastName = trim((string)($meta['_Cognome'] ?? ''));
                if ($firstName === '' && $attendee !== '') {
                    $parts = preg_split('/\s+/u', $attendee, 2) ?: [];
                    $firstName = (string)($parts[0] ?? '');
                    if ($lastName === '') $lastName = (string)($parts[1] ?? '');
                }
                $rows[] = ['legacy_registration_id'=>$sourceId,'legacy_event_id'=>(int)$wp->post_parent,'source_event_title'=>(string)($meta['_event_registered_for']??''),'first_name'=>$firstName,'last_name'=>$lastName,'email'=>(string)($meta['_attendee_email']??''),'phone'=>(string)($meta['_Recapito_telefonico']??$meta['_Telefono']??''),'company'=>(string)($meta['_Azienda']??''),'registered_at'=>(string)$wp->post_date,'checked_in'=>self::truthy($meta['_check_in']??false),'checked_in_at'=>(string)($meta['_checkin_time']??''),'possible_duplicate'=>false,'send_notifications'=>false];
            }
            if ($rows === []) throw new RuntimeException('Nell’XML non sono state trovate iscrizioni Campus (event_registration).');
            return ['format'=>'idemaclima_event_registrations_v1','source'=>'wordpress_xml','summary'=>['registrations_to_import'=>count($rows)],'options'=>['send_notifications'=>false],'registrations'=>$rows];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private static function truthy(mixed $value): bool
    {
        return in_array(strtolower(trim((string)$value)), ['1','true','yes','si','sì','on'], true);
    }

    private static function validatePayload(array $payload, array $rows): void
    {
        if (($payload['format'] ?? '') !== 'idemaclima_event_registrations_v1') throw new RuntimeException('Formato manifest non riconosciuto.');
        if($rows===[]) throw new RuntimeException('Il file non contiene registrazioni.'); $declared=(int)($payload['summary']['registrations_to_import']??count($rows)); if($declared!==count($rows)) throw new RuntimeException('Il riepilogo del file non coincide con le registrazioni trovate.');
        if (($payload['options']['send_notifications'] ?? true) !== false) throw new RuntimeException('Le notifiche devono essere disattivate.');
        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row) || ($row['send_notifications'] ?? true) !== false) throw new RuntimeException('Una registrazione richiede notifiche: importazione bloccata.');
            $sourceId = (int)($row['legacy_registration_id'] ?? 0);
            if ($sourceId < 1 || isset($ids[$sourceId])) throw new RuntimeException('ID WordPress mancante o duplicato.');
            $ids[$sourceId] = true;
        }
    }

    private static function eventIds(PDO $pdo, bool $execute): array
    {
        $slugs = array_values(array_unique(self::EVENT_SLUGS));
        $marks = implode(',', array_fill(0, count($slugs), '?'));
        $stmt = $pdo->prepare("SELECT id,slug FROM events WHERE slug IN ($marks)");
        $stmt->execute($slugs);
        $ids = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $ids[(string)$row['slug']] = (int)$row['id'];
        if (count($ids) !== count($slugs)) throw new RuntimeException('Uno o più eventi Campus di destinazione non sono presenti.');
        foreach (self::historicalEvents() as $key => $event) {
            $find = $pdo->prepare('SELECT id FROM events WHERE slug=?'); $find->execute([$event['slug']]);
            $id = $find->fetchColumn();
            if (!$id && $execute) {
                $insert = $pdo->prepare('INSERT INTO events(title,slug,audience,category,starts_at,short_description,description,registration_open,published,cancelled,archived,sort_order) VALUES(?,?,?,?,NULL,?,?,0,0,0,1,?)');
                $insert->execute([$event['title'],$event['slug'],'public','Altro',$event['note'],$event['note'],$event['sort_order']]);
                $id = $pdo->lastInsertId();
            }
            $ids[$key] = $id ? (int)$id : -1;
        }
        return $ids;
    }

    private static function process(PDO $pdo, array $rows, array $eventIds, bool $execute, array $selectedIds=[]): array
    {
        $validated = $imported = $skipped = 0; $errors = []; $toImport = [];
        $check = $pdo->prepare('SELECT id,event_id FROM event_registrations WHERE source_wordpress_id=?');
        $updateEvent = $pdo->prepare('UPDATE event_registrations SET event_id=? WHERE id=?');
        $insert = $pdo->prepare('INSERT INTO event_registrations(source_wordpress_id,event_id,first_name,last_name,email,phone,company,role,notes,status,attended,legacy_checked_in_at,privacy_accepted_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($rows as $row) {
                $sourceId = (int)$row['legacy_registration_id'];
                if (str_starts_with(mb_strtoupper(trim((string)($row['source_event_title'] ?? '')), 'UTF-8'), 'TEST:')) { $skipped++; continue; }
                $eventId = self::destinationEventId($row, $eventIds);
                if ($eventId < 1 && $execute) throw new RuntimeException('#'.$sourceId.': evento di destinazione non disponibile.');
                if (!filter_var((string)($row['email'] ?? ''), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('#'.$sourceId.': email non valida.');
                $check->execute([$sourceId]);
                $existing=$check->fetch(PDO::FETCH_ASSOC);
                if ($existing && (int)$existing['event_id'] === $eventId) { $skipped++; continue; }
                if($execute&&!in_array($sourceId,$selectedIds,true))continue;
                $validated++;
                $toImport[]=['id'=>$sourceId,'nome'=>trim((string)($row['first_name']??'').' '.(string)($row['last_name']??'')),'email'=>strtolower(trim((string)($row['email']??''))),'evento'=>(string)($row['source_event_title']??''),'realign'=>(bool)$existing];
                if (!$execute) continue;
                if ($existing) { $updateEvent->execute([$eventId,(int)$existing['id']]); $imported++; continue; }
                $created = self::date((string)($row['registered_at'] ?? ''));
                $checked = empty($row['checked_in_at']) ? null : self::date((string)$row['checked_in_at']);
                $notes = !empty($row['possible_duplicate']) ? 'Possibile duplicato segnalato durante la migrazione WordPress.' : null;
                $insert->execute([$sourceId,$eventId,self::name($row['first_name'] ?? ''),self::name($row['last_name'] ?? ''),strtolower(trim((string)$row['email'])),trim((string)($row['phone'] ?? '')) ?: null,Validator::companyName($row['company'] ?? '') ?: null,null,$notes,'registered',($row['checked_in'] ?? false) ? 1 : null,$checked,$created,$created]);
                $imported++;
        }
        $groups=[];foreach($toImport as $item){$key=strtolower(trim((string)$item['email'])).'|'.mb_strtolower(trim((string)$item['evento']),'UTF-8');$groups[$key]=($groups[$key]??0)+1;}foreach($toImport as &$item){$key=strtolower(trim((string)$item['email'])).'|'.mb_strtolower(trim((string)$item['evento']),'UTF-8');$item['possible_duplicate']=($groups[$key]??0)>1;}unset($item);
        return ['validated'=>$validated,'imported'=>$imported,'skipped'=>$skipped,'to_import'=>$toImport,'errors'=>$errors];
    }

    private static function verifySelected(PDO $pdo, array $selectedIds): void
    {
        if ($selectedIds === []) throw new RuntimeException('Nessuna iscrizione selezionata.');
        $marks = implode(',', array_fill(0, count($selectedIds), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM event_registrations WHERE source_wordpress_id IN ($marks) AND event_id IS NOT NULL");
        $stmt->execute($selectedIds);
        if ((int)$stmt->fetchColumn() !== count($selectedIds)) throw new RuntimeException('Importazione incompleta: una o più iscrizioni non sono state associate al corso. Nessun dato è stato confermato.');
    }

    private static function destinationEventId(array $row, array $eventIds): int
    {
        $legacy = (string)($row['legacy_event_id'] ?? '');
        if (isset(self::EVENT_SLUGS[$legacy])) return $eventIds[self::EVENT_SLUGS[$legacy]] ?? 0;
        $title = mb_strtolower((string)($row['source_event_title'] ?? ''), 'UTF-8');
        $key = str_contains($title, 'analisi fabbisogni energetici') ? 'historical-analysis' : 'historical-pdc-acs';
        return $eventIds[$key] ?? 0;
    }

    private static function historicalEvents(): array
    {
        $note = 'Evento storico importato da WordPress; evento originale eliminato e data non disponibile.';
        return [
            'historical-pdc-acs'=>['slug'=>'storico-pdc-idronici-residenziali-acs-data-non-disponibile','title'=>'Sistemi in PdC idronici e sistemi residenziali con produzione di ACS','note'=>$note,'sort_order'=>-20],
            'historical-analysis'=>['slug'=>'storico-analisi-fabbisogni-energetici-data-non-disponibile','title'=>'Analisi fabbisogni energetici e sistemi in pompa di calore','note'=>$note,'sort_order'=>-10],
        ];
    }

    private static function date(string $value): string
    { $dt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', trim($value)); if (!$dt) throw new RuntimeException('Data storica non valida.'); return $dt->format('Y-m-d H:i:s'); }
    private static function name(mixed $value): string
    { $v=trim(preg_replace('/\s+/u',' ',(string)$value)??(string)$value); return $v===''?'':mb_strtoupper($v,'UTF-8'); }
    private static function respond(array $payload): void
    { header('Content-Type: application/json; charset=UTF-8'); echo json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); }
}