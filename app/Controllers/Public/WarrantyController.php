<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Database;
use App\Core\PrivateUpload;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Services\WarrantyService;
use DateTimeImmutable;
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
        if (!RateLimiter::allow('warranty-submit', 5, 1800)) RateLimiter::reject(1800);
        if (!Security::verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            self::render('warranty/result', ['title' => 'Sessione scaduta', 'success' => false, 'message' => 'Sessione non valida. Ricarica il modulo e riprova.']);
            return;
        }

        // Honeypot: i browser normali non compilano questo campo.
        if (trim((string)($_POST['company_website'] ?? '')) !== '') {
            self::render('warranty/result', [
                'title' => 'Registrazione ricevuta',
                'success' => true,
                'message' => 'La richiesta è stata ricevuta.',
            ]);
            return;
        }

        $pdo = Database::connection();
        $old = array_map(static fn($v) => is_string($v) ? trim($v) : $v, $_POST);
        unset($old['company_website']);
        $errors = [];

        $productType = (string)($old['product_type'] ?? '');
        $outerUnit = $productType === 'multi' ? (string)($old['multi_outer'] ?? '') : '';
        $combination = $productType === 'mono'
            ? (string)($old['mono_combination'] ?? '')
            : (string)($old['multi_combination'] ?? '');

        if (!in_array($productType, ['mono', 'multi'], true)) $errors[] = 'Seleziona la tipologia di sistema.';
        if ($combination === '' || mb_strlen($combination) > 255 || !preg_match('/^[A-Z0-9+ .\-x]+$/i', $combination)) {
            $errors[] = 'Seleziona una combinazione valida.';
        }
        $allowedOuter = ['2MIT-50-R32','3MIT-78-R32','2MWTZ-50-R32','2MW-50-R32','3MWTZ-70-R32','3MW-70-R32'];
        if ($productType === 'multi' && !in_array($outerUnit, $allowedOuter, true)) $errors[] = 'Seleziona un’unità esterna Multi Split valida.';
        if ($productType === 'multi' && str_contains($outerUnit, 'MIT') && !str_contains($combination, 'ISPT')) $errors[] = 'La combinazione non è compatibile con l’unità esterna selezionata.';
        if ($productType === 'multi' && !str_contains($outerUnit, 'MIT') && !preg_match('/WTZ|WTMC/', $combination)) $errors[] = 'La combinazione non è compatibile con l’unità esterna selezionata.';

        $modelId = WarrantyService::modelIdFromCombination($pdo, $combination);
        $invoiceDate = (string)($old['invoice_date'] ?? '');

        $required = [
            'customer_first_name' => 120,
            'customer_last_name' => 120,
            'fiscal_code' => 32,
            'email' => 190,
            'address' => 255,
            'postal_code' => 12,
            'city' => 120,
            'province' => 8,
            'region' => 120,
            'invoice_date' => 10,
            'outdoor_serial' => 160,
        ];
        foreach ($required as $field => $max) {
            $value = trim((string)($old[$field] ?? ''));
            if ($value === '') {
                $errors[] = 'Compila tutti i campi obbligatori.';
                continue;
            }
            if (mb_strlen($value) > $max) $errors[] = 'Uno o più campi superano la lunghezza consentita.';
        }

        $phone = trim((string)($old['phone'] ?? ''));
        if ($phone !== '' && (mb_strlen($phone) > 50 || !preg_match('/^[0-9+().\-\s]{5,50}$/', $phone))) {
            $errors[] = 'Numero di telefono non valido.';
        }

        if ($modelId < 1) $errors[] = 'Seleziona un modello.';
        if (!filter_var((string)($old['email'] ?? ''), FILTER_VALIDATE_EMAIL)) $errors[] = 'Indirizzo email non valido.';

        $fiscal = strtoupper(preg_replace('/\s+/', '', (string)($old['fiscal_code'] ?? '')) ?? '');
        if (!preg_match('/^(?:[A-Z0-9]{16}|\d{11})$/', $fiscal)) $errors[] = 'Codice fiscale o Partita IVA non valido.';

        $postal = strtoupper(trim((string)($old['postal_code'] ?? '')));
        if (!preg_match('/^[A-Z0-9 -]{3,12}$/', $postal)) $errors[] = 'CAP non valido.';

        $province = strtoupper(trim((string)($old['province'] ?? '')));
        if (!preg_match('/^[A-Z]{2}$/', $province)) $errors[] = 'Provincia non valida: usa la sigla di 2 lettere.';

        $invoice = DateTimeImmutable::createFromFormat('!Y-m-d', $invoiceDate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        if (!$invoice || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $invoice->format('Y-m-d') !== $invoiceDate) {
            $errors[] = 'Data fattura non valida.';
        } elseif ($invoice > new DateTimeImmutable('today')) {
            $errors[] = 'La data fattura non può essere futura.';
        }

        if (!isset($old['privacy'])) $errors[] = 'È necessario accettare l’informativa privacy.';

        $rule = ($modelId > 0 && $invoice) ? WarrantyService::applicableRule($pdo, $modelId, $invoiceDate) : null;
        if (!$rule && $modelId > 0 && $invoice) $errors[] = 'Il modello selezionato non risulta abilitato all’estensione di garanzia per la data indicata.';
        if ($rule && !WarrantyService::registrationWithinLimit($rule, $invoiceDate)) {
            $errors[] = 'Il termine previsto per la registrazione della garanzia risulta superato.';
        }

        $indoorSerials = [];
        for ($index = 1; $index <= 3; $index++) {
            $serial = trim((string)($old['indoor_serial_' . $index] ?? ''));
            if ($serial !== '') $indoorSerials[] = $serial;
        }

        $expectedIndoor = 1;
        if ($productType === 'multi') {
            $expectedIndoor = 0;
            foreach (preg_split('/\s*\+\s*/', $combination) ?: [] as $unitPart) {
                $quantity = 1;
                if (preg_match('/^\s*(\d+)\s*x\b/i', $unitPart, $quantityMatch)) {
                    $quantity = max(1, (int)$quantityMatch[1]);
                }
                $expectedIndoor += $quantity;
            }
            $expectedIndoor = max(1, min(3, $expectedIndoor));
        }

        if (count($indoorSerials) !== $expectedIndoor) {
            $errors[] = 'Inserisci esattamente ' . $expectedIndoor . ' serial' . ($expectedIndoor === 1 ? 'e' : 'i') . ' per le unità interne.';
        }
        foreach ($indoorSerials as $serial) {
            if (mb_strlen($serial) > 160) $errors[] = 'Uno o più seriali delle unità interne sono troppo lunghi.';
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

        $uploadErrors = [];
        $invoiceFile = PrivateUpload::warrantyDocument('invoice_file', $uploadErrors);
        $fgasFile = PrivateUpload::warrantyDocument('fgas_file', $uploadErrors);
        if ($rule && (int)$rule['invoice_required'] === 1 && !$invoiceFile) $uploadErrors[] = 'Allega la fattura di acquisto.';
        if ($rule && (int)$rule['fgas_required'] === 1 && !$fgasFile) $uploadErrors[] = 'Allega la documentazione F-GAS richiesta.';

        if ($uploadErrors) {
            PrivateUpload::remove($invoiceFile['path'] ?? null);
            PrivateUpload::remove($fgasFile['path'] ?? null);
            self::render('warranty/form', [
                'title' => 'Estensione di garanzia',
                'models' => WarrantyService::eligibleModels($pdo),
                'csrf' => Security::csrfToken(),
                'errors' => array_values(array_unique($uploadErrors)),
                'old' => $old,
            ]);
            return;
        }

        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS warranty_registration_details (
                    registration_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                    product_type VARCHAR(20) NOT NULL,
                    outer_unit VARCHAR(80) NULL,
                    combination VARCHAR(255) NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_warranty_details_type (product_type)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'INSERT INTO warranty_registrations
                 (model_id, warranty_rule_id, warranty_years, extension_formula, registration_days_limit,
                  invoice_required_snapshot, fgas_required_snapshot,
                  customer_first_name, customer_last_name, fiscal_code, email, phone, address, postal_code, city, province, region,
                  invoice_date, invoice_file, fgas_file, privacy_accepted_at, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),"pending")'
            );
            $stmt->execute([
                $modelId,
                (int)$rule['id'],
                (int)$rule['warranty_years'],
                $rule['extension_formula'],
                $rule['registration_days_limit'],
                (int)$rule['invoice_required'],
                (int)$rule['fgas_required'],
                $old['customer_first_name'],
                $old['customer_last_name'],
                $fiscal,
                strtolower((string)$old['email']),
                $phone !== '' ? $phone : null,
                $old['address'],
                $postal,
                strtoupper((string)$old['city']),
                $province,
                $old['region'],
                $invoiceDate,
                $invoiceFile['path'] ?? null,
                $fgasFile['path'] ?? null,
            ]);
            $registrationId = (int)$pdo->lastInsertId();

            $detail = $pdo->prepare(
                'INSERT INTO warranty_registration_details (registration_id, product_type, outer_unit, combination) VALUES (?,?,?,?)'
            );
            $detail->execute([$registrationId, $productType, $outerUnit !== '' ? $outerUnit : null, $combination]);

            $unit = $pdo->prepare('INSERT INTO warranty_units (registration_id, model_id, unit_type, serial_number) VALUES (?,?,?,?)');
            $unit->execute([$registrationId, $modelId, 'outdoor', trim((string)$old['outdoor_serial'])]);
            foreach ($indoorSerials as $serial) {
                $unit->execute([$registrationId, null, 'indoor', $serial]);
            }

            $pdo->commit();

            WarrantyService::notifyInternal(
                'Nuova registrazione garanzia IDEMA #' . $registrationId,
                [
                    'Pratica' => '#' . $registrationId,
                    'Sistema' => ($productType === 'mono' ? 'Mono Split - ' : 'Multi Split - ' . $outerUnit . ' / ') . $combination,
                    'Copertura' => (string)$rule['warranty_years'] . ' anni' . ($rule['extension_formula'] ? ' (' . $rule['extension_formula'] . ')' : ''),
                    'Pannello' => 'https://www.rappresentanzeguanzirolisas.it/idemaclima/admin/warranties/registration?id=' . $registrationId,
                ]
            );
            self::render('warranty/result', [
                'title' => 'Registrazione ricevuta',
                'success' => true,
                'message' => 'La richiesta di estensione garanzia è stata registrata e sarà sottoposta a verifica.',
                'registrationId' => $registrationId,
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            PrivateUpload::remove($invoiceFile['path'] ?? null);
            PrivateUpload::remove($fgasFile['path'] ?? null);
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
