<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;

final class WarrantyService
{
    public static function applicableRule(PDO $pdo, int $modelId, ?string $invoiceDate = null): ?array
    {
        $date = $invoiceDate !== null && self::isIsoDate($invoiceDate) ? $invoiceDate : null;
        $stmt = $pdo->prepare(
            'SELECT wr.*, pm.product_id
             FROM product_models pm
             JOIN warranty_rules wr ON wr.enabled = 1 AND (wr.model_id = pm.id OR (wr.model_id IS NULL AND wr.product_id = pm.product_id))
             WHERE pm.id = ?
               AND (wr.valid_from IS NULL OR wr.valid_from <= COALESCE(?, CURRENT_DATE))
               AND (wr.valid_to IS NULL OR wr.valid_to >= COALESCE(?, CURRENT_DATE))
             ORDER BY (wr.model_id = pm.id) DESC, wr.id DESC
             LIMIT 1'
        );
        $stmt->execute([$modelId, $date, $date]);
        $rule = $stmt->fetch(PDO::FETCH_ASSOC);
        return $rule ?: null;
    }

    public static function eligibleModels(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT DISTINCT pm.id, pm.code, pm.sort_order, p.name AS product_name, c.name AS family_name
             FROM product_models pm
             JOIN products p ON p.id = pm.product_id AND p.published = 1
             JOIN product_categories c ON c.id = p.category_id AND c.published = 1
             JOIN warranty_rules wr ON wr.enabled = 1 AND (wr.model_id = pm.id OR (wr.model_id IS NULL AND wr.product_id = p.id))
             WHERE pm.published = 1
               AND (wr.valid_from IS NULL OR wr.valid_from <= CURRENT_DATE)
               AND (wr.valid_to IS NULL OR wr.valid_to >= CURRENT_DATE)
             ORDER BY p.name, pm.sort_order, pm.code'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function modelIdFromCombination(PDO $pdo, string $combination): int
    {
        $combination = strtoupper(trim($combination));
        if ($combination === '') return 0;
        $models = self::eligibleModels($pdo);
        usort($models, static fn(array $a,array $b):int => strlen((string)$b['code']) <=> strlen((string)$a['code']));
        foreach ($models as $model) {
            $code = strtoupper(trim((string)$model['code']));
            if ($code === '') continue;
            $base = preg_replace('/-R32$/', '', $code) ?: $code;
            if (str_contains($combination, $code) || str_contains($combination, $base)) return (int)$model['id'];
        }
        return 0;
    }

    public static function registrationWithinLimit(array $rule, string $invoiceDate): bool
    {
        $limit = isset($rule['registration_days_limit']) ? (int)$rule['registration_days_limit'] : 0;
        if ($limit <= 0) return true;
        if (!self::isIsoDate($invoiceDate)) return false;
        $invoice = DateTimeImmutable::createFromFormat('!Y-m-d', $invoiceDate);
        if (!$invoice) return false;
        $deadline = $invoice->modify('+' . $limit . ' days')->setTime(23, 59, 59);
        return new DateTimeImmutable('now') <= $deadline;
    }

    public static function isIsoDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format('Y-m-d') === $value;
    }

    public static function notificationRecipient(): string
    {
        return self::notificationRecipients()[0];
    }

    public static function notifyInternal(string $subject, array $rows, ?string $replyTo = null): bool
    {
        $settings = self::emailSettings();
        if (in_array(strtolower(trim((string)($settings['notifications_enabled'] ?? '1'))), ['0','false','no','off'], true)) {
            return true;
        }
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? '');
        if ($subject === '') $subject = 'Nuova richiesta dal sito IDEMA';

        $body = "È stata ricevuta una nuova richiesta dal sito IDEMA.\n\n";
        foreach ($rows as $label => $value) {
            $label = trim(preg_replace('/[\r\n]+/', ' ', (string)$label) ?? '');
            $value = trim((string)$value);
            if ($value !== '') $body .= $label . ': ' . $value . "\n";
        }
        $body .= "\nAccedi al pannello amministrativo IDEMA per verificare i dati e gli eventuali allegati.\n";

        $from = filter_var($settings['sender_email'] ?? '', FILTER_VALIDATE_EMAIL)
            ? (string)$settings['sender_email']
            : 'no-reply@rappresentanzeguanzirolisas.it';
        $senderName = trim((string)preg_replace('/[\r\n]+/', ' ', (string)($settings['sender_name'] ?? 'Idema Clima Srl')));
        if (in_array(strtolower($senderName), ['idema clima', 'idema sito web'], true)) $senderName = 'Idema Clima Srl';
        $headers = [
            'From: ' . $senderName . ' <' . $from . '>',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: IDEMA Website',
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $encodedSubject = function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($subject, 'UTF-8')
            : $subject;
        $sent = true;
        foreach (self::notificationRecipients($settings) as $recipient) {
            if (!@mail($recipient, $encodedSubject, $body, implode("\r\n", $headers))) {
                $sent = false;
                error_log('IDEMA notification email not sent: ' . $subject);
            }
        }
        return $sent;
    }

    private static function notificationRecipients(?array $settings = null): array
    {
        $settings ??= self::emailSettings();
        $values = preg_split('/[;,\s]+/', trim((string)($settings['warranty_recipients'] ?? ''))) ?: [];
        $valid = array_values(array_unique(array_filter(
            $values,
            static fn(string $email): bool => (bool)filter_var($email, FILTER_VALIDATE_EMAIL)
        )));
        return $valid ?: ['commerciale.tre@idemaclima.it'];
    }

    private static function emailSettings(): array
    {
        static $settings = null;
        if ($settings !== null) return $settings;
        try {
            $stmt = Database::connection()->query("SELECT setting_key,setting_value FROM site_settings WHERE setting_group='email'");
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (\Throwable) {
            $settings = [];
        }
        return $settings;
    }

}
