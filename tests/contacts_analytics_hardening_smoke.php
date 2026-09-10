<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$contact = file_get_contents($root . '/app/Controllers/Public/ContactController.php');
$analytics = file_get_contents($root . '/app/Controllers/Public/AnalyticsController.php');
$admin = file_get_contents($root . '/app/Controllers/Admin/ContactsAnalyticsController.php');
$layout = file_get_contents($root . '/app/Views/public/_layout_start.php');
$form = file_get_contents($root . '/app/Views/public/contact/form.php');
$upload = file_get_contents($root . '/app/Core/PrivateUpload.php');
$migration = file_get_contents($root . '/database/migrations/022_contacts_analytics_privacy.sql');

foreach ([$contact,$analytics,$admin,$layout,$form,$upload,$migration] as $content) {
    if (!is_string($content) || $content === '') throw new RuntimeException('File contatti/analytics non leggibile.');
}

$checks = [
    [$contact, 'company_website', 'honeypot contatti'],
    [$contact, "mb_strlen", 'limiti server-side'],
    [$analytics, "hash_hmac('sha256',date('Y-m-d').'|'", 'hash giornaliero pseudonimo'],
    [$analytics, 'referrerPath', 'referrer ridotto al path'],
    [$analytics, "VALUES(?,'download',?,?,NULL)", 'UA non persistito'],
    [$analytics, 'internal_tracking_retention_days', 'retention tracking interno'],
    [$admin, "Audit::log('contact.update'", 'audit contatti'],
    [$admin, 'Cache-Control: private, no-store', 'no-store allegati'],
    [$admin, 'consent_required', 'consenso analytics amministrabile'],
    [$layout, 'idema_analytics_consent', 'gate consenso GA4'],
    [$form, 'company_website', 'honeypot nel form'],
    [$upload, 'allowOfficeZipDetection', 'riconoscimento OpenXML'],
    [$migration, 'internal_tracking_retention_days', 'schema retention'],
];

foreach ($checks as [$haystack,$needle,$label]) {
    if (!str_contains($haystack, $needle)) throw new RuntimeException('Check contatti/analytics fallito: ' . $label);
}

if (str_contains($form, '.zip')) throw new RuntimeException('Il modulo contatti non deve accettare ZIP arbitrari.');

fwrite(STDOUT, "Contacts/analytics hardening smoke OK\n");
