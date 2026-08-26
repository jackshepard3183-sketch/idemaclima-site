<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\PrivateUpload;
use App\Core\Security;
use App\Services\WarrantyService;
use PDO;
use Throwable;

final class WarrantyController
{
    public static function form(): void
    {
        $pdo = Database::connection();
        self::render('warranty/form', [
            'title' => 'Estensione di garanzia',
            'models' => WarrantyService::eligibleModels($pdo),
            'csrf' => Security::csrfToken(),
            'errors' => [],
            'old' => [],
        ]);
    }

    public static function register(): void
    {
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            self::render('warranty/result', ['title' => 'Sessione scaduta', 'success' => false, 'message' => 'Sessione non valida. Ricarica il modulo e riprova.']);
            return;
        }

        $pdo = Database::connection();
        $old = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $_POST);
        $errors = [];
        $modelId = (int)($old['model_id'] ?? 0);
        $invoiceDate = (string)($old['invoice_date'] ?? '');

        foreach (['customer_first_name','customer_last_name','fiscal_code','email','address','postal_code','city','province','region','invoice_date','outdoor_serial'] as $field) {
            if (trim((string)($old[$field] ?? '')) === '') $errors[] = 'Compila tutti i campi obbligatori.';
        }
        if ($modelId < 1) $errors[] = 'Seleziona un modello.';
        if (!filter_var((string)($old['email'] ?? ''), FILTER_VALIDATE_EMAIL)) $errors[] = 'Indirizzo email non valido.';
        $fiscal = strtoupper(preg_replace('/\s+/', '', (string)($old['fiscal_code'] ?? '')) ?? '');
        if (!preg_match('/^(?:[A-Z0-9]{16}|\d{11})$/', $fiscal)) $errors[] = 'Codice fiscale o Partita IVA non valido.';
        if (!isset($old['privacy'])) $errors[] = 'È necessario accettare l’informativa privacy.';

        $rule = $modelId > 0 ? WarrantyService::applicableRule($pdo, $modelId, $invoiceDate ?: null) : null;
        if (!$rule) $errors[] = 'Il modello selezionato non risulta abilitato all’estensione di garanzia.';
        if ($rule && $invoiceDate !== '' && !WarrantyService::registrationWithinLimit($rule, $invoiceDate)) {
            $errors[] = 'Il termine previsto per la registrazione della garanzia risulta superato.';
        }

        if ($errors) {
            self::render('warranty/form', [
                'title' => 'Estensione di garanzia',
                'models' => WarrantyService::eligibleModels($pdo),
                'csrf' => Security::csrfToken(),
                'errors' => array_values(array_unique($errors)),
                'old' => $old,
            ]);
            return;
        }

        $invoice = PrivateUpload::warrantyDocument('invoice_file', $errors);
        $fgas = PrivateUpload::warrantyDocument('fgas_file', $errors);
        if ($rule && (int)$rule['invoice_required'] === 1 && !$invoice) $errors[] = 'Allega la fattura di acquisto.';
        if ($rule && (int)$rule['fgas_required'] === 1 && !$fgas) $errors[] = 'Allega la documentazione F-GAS richiesta.';

        if ($errors) {
            self::render('warranty/form', [
                'title' => 'Estensione di garanzia',
                'models' => WarrantyService::eligibleModels($pdo),
                'csrf' => Security::csrfToken(),
                'errors' => array_values(array_unique($errors)),
                'old' => $old,
            ]);
            return;
        }

        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO warranty_registrations
                 (model_id, warranty_rule_id, warranty_years, extension_formula, registration_days_limit,
                  customer_first_name, customer_last_name, fiscal_code, email, phone, address, postal_code, city, province, region,
                  invoice_date, invoice_file, fgas_file, privacy_accepted_at, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),"pending")'
            );
            $stmt->execute([
                $modelId, (int)$rule['id'], (int)$rule['warranty_years'], $rule['extension_formula'], $rule['registration_days_limit'],
                $old['customer_first_name'], $old['customer_last_name'], $fiscal, $old['email'], $old['phone'] ?: null,
                $old['address'], $old['postal_code'], strtoupper((string)$old['city']), strtoupper((string)$old['province']), $old['region'],
                $invoiceDate, $invoice['path'], $fgas['path'] ?? null,
            ]);
            $registrationId = (int)$pdo->lastInsertId();

            $unit = $pdo->prepare('INSERT INTO warranty_units (registration_id, model_id, unit_type, serial_number) VALUES (?,?,?,?)');
            $unit->execute([$registrationId, $modelId, 'outdoor', trim((string)$old['outdoor_serial'])]);

            $indoor = preg_split('/\R+/', trim((string)($old['indoor_serials'] ?? ''))) ?: [];
            foreach ($indoor as $serial) {
                $serial = trim($serial);
                if ($serial !== '') $unit->execute([$registrationId, null, 'indoor', $serial]);
            }

            $pdo->commit();
            self::render('warranty/result', [
                'title' => 'Registrazione ricevuta',
                'success' => true,
                'message' => 'La richiesta di estensione garanzia è stata registrata e sarà sottoposta a verifica.',
                'registrationId' => $registrationId,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            http_response_code(500);
            self::render('warranty/result', ['title' => 'Errore', 'success' => false, 'message' => 'Non è stato possibile registrare la richiesta.']);
        }
    }

    private static function render(string $view, array $data): void
    {
        extract($data, EXTR_SKIP);
        header('Content-Type: text/html; charset=UTF-8');
        require dirname(__DIR__, 2) . '/Views/public/_layout_start.php';
        require dirname(__DIR__, 2) . '/Views/public/' . $view . '.php';
        require dirname(__DIR__, 2) . '/Views/public/_layout_end.php';
    }
}
