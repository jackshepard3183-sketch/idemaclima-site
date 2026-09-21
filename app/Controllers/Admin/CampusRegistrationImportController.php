<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\AdminAuth;
use App\Core\Database;
use App\Core\Security;
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
        '11190' => 'r290-prodotti-sicurezza-limiti-operativi-r32-r410a-08-10-2026',
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
        try {
            $payload = self::payload();
            $rows = $payload['registrations'];
            self::validatePayload($payload, $rows);
            $pdo = Database::connection();
            if ($execute) $pdo->beginTransaction();
            try {
                $eventIds = self::eventIds($pdo, $execute);
                $result = self::process($pdo, $rows, $eventIds, $execute);
                if ($execute) $pdo->commit();
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
        if (!is_array($file) || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) throw new RuntimeException('Seleziona il manifest JSON.');
        if ((int)($file['size'] ?? 0) > 2097152) throw new RuntimeException('Il file supera 2 MB.');
        $payload = json_decode((string)file_get_contents((string)$file['tmp_name']), true);
        if (!is_array($payload) || !is_array($payload['registrations'] ?? null)) throw new RuntimeException('Manifest non valido.');
        return $payload;
    }

    private static function validatePayload(array $payload, array $rows): void
    {
        if (($payload['format'] ?? '') !== 'idemaclima_event_registrations_v1') throw new RuntimeException('Formato manifest non riconosciuto.');
        if (count($rows) !== 132 || (int)($payload['summary']['registrations_to_import'] ?? 0) !== 132) throw new RuntimeException('Il lotto deve contenere esattamente 132 registrazioni.');
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

    private static function process(PDO $pdo, array $rows, array $eventIds, bool $execute): array
    {
        $validated = $imported = $skipped = 0; $errors = [];
        $check = $pdo->prepare('SELECT id FROM event_registrations WHERE source_wordpress_id=?');
        $insert = $pdo->prepare('INSERT INTO event_registrations(source_wordpress_id,event_id,first_name,last_name,email,phone,company,role,notes,status,attended,legacy_checked_in_at,privacy_accepted_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach ($rows as $row) {
                $sourceId = (int)$row['legacy_registration_id'];
                $eventId = self::destinationEventId($row, $eventIds);
                if ($eventId < 1 && $execute) throw new RuntimeException('#'.$sourceId.': evento di destinazione non disponibile.');
                if (!filter_var((string)($row['email'] ?? ''), FILTER_VALIDATE_EMAIL)) throw new RuntimeException('#'.$sourceId.': email non valida.');
                $check->execute([$sourceId]);
                if ($check->fetchColumn()) { $skipped++; continue; }
                $validated++;
                if (!$execute) continue;
                $created = self::date((string)($row['registered_at'] ?? ''));
                $checked = empty($row['checked_in_at']) ? null : self::date((string)$row['checked_in_at']);
                $notes = !empty($row['possible_duplicate']) ? 'Possibile duplicato segnalato durante la migrazione WordPress.' : null;
                $insert->execute([$sourceId,$eventId,self::name($row['first_name'] ?? ''),self::name($row['last_name'] ?? ''),strtolower(trim((string)$row['email'])),trim((string)($row['phone'] ?? '')) ?: null,self::name($row['company'] ?? '') ?: null,null,$notes,'registered',($row['checked_in'] ?? false) ? 1 : null,$checked,$created,$created]);
                $imported++;
        }
        return ['validated'=>$validated + $skipped,'imported'=>$imported,'skipped'=>$skipped,'errors'=>$errors];
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
    { $v=trim(preg_replace('/\s+/u',' ',(string)$value)??(string)$value); return $v===''?'':mb_convert_case(mb_strtolower($v,'UTF-8'),MB_CASE_TITLE,'UTF-8'); }
    private static function respond(array $payload): void
    { header('Content-Type: application/json; charset=UTF-8'); echo json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE); }
}
