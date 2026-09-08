<?php

declare(strict_types=1);

namespace App\Services;

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
        return 'commerciale.tre@idemaclima.it';
    }

    public static function notifyInternal(string $subject, array $rows, ?string $replyTo = null): bool
    {
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject) ?? '');
        if ($subject === '') $subject = 'Nuova richiesta dal sito IDEMA';

        $body = "È stata ricevuta una nuova richiesta dal sito IDEMA.\n\n";
        foreach ($rows as $label => $value) {
            $label = trim(preg_replace('/[\r\n]+/', ' ', (string)$label) ?? '');
            $value = trim((string)$value);
            if ($value !== '') $body .= $label . ': ' . $value . "\n";
        }
        $body .= "\nAccedi al pannello amministrativo IDEMA per verificare i dati e gli eventuali allegati.\n";

        $headers = [
            'From: IDEMA sito web <no-reply@rappresentanzeguanzirolisas.it>',
            'Content-Type: text/plain; charset=UTF-8',
            'X-Mailer: IDEMA Website',
        ];
        if ($replyTo !== null && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $encodedSubject = function_exists('mb_encode_mimeheader')
            ? mb_encode_mimeheader($subject, 'UTF-8')
            : $subject;
        $sent = @mail(self::notificationRecipient(), $encodedSubject, $body, implode("\r\n", $headers));
        if (!$sent) error_log('IDEMA notification email not sent: ' . $subject);
        return $sent;
    }

}