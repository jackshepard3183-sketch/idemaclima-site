<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/app/Controllers/Admin/CampusController.php');
$public = file_get_contents($root . '/app/Controllers/Public/CampusController.php');
$auth = file_get_contents($root . '/app/Auth/CatAuth.php');
$eventForm = file_get_contents($root . '/app/Views/admin/campus_event_form.php');
$catForm = file_get_contents($root . '/app/Views/admin/cat_user_form.php');
$eventView = file_get_contents($root . '/app/Views/public/campus/event.php');
$migration = file_get_contents($root . '/database/migrations/019_campus_security.sql');
$completeMigration = file_get_contents($root . '/database/migrations/027_campus_complete.sql');
$actions = file_get_contents($root . '/app/Controllers/Admin/CampusActionsController.php');
$indexView = file_get_contents($root . '/app/Views/public/campus/index.php');

foreach ([$admin,$public,$auth,$eventForm,$catForm,$eventView,$migration,$completeMigration,$actions,$indexView] as $content) {
    if (!is_string($content) || $content === '') throw new RuntimeException('File Campus/CAT non leggibile.');
}

$checks = [
    [$admin, "campus.event.create", 'audit creazione evento'],
    [$admin, "campus.registration.update", 'audit iscrizione'],
    [$admin, "cat_account.update", 'audit CAT'],
    [$admin, "strongPassword", 'password forte CAT'],
    [$admin, "cover_image_file", 'upload copertina'],
    [$public, "FOR UPDATE", 'lock capienza'],
    [$public, "company_website", 'honeypot Campus'],
    [$public, "cancelled=0", 'eventi annullati esclusi'],
    [$public, "waitlist_enabled", 'lista attesa configurabile'],
    [$auth, "verified_at IS NOT NULL", 'account CAT verificato'],
    [$auth, "password_needs_rehash", 'rehash password'],
    [$eventForm, "multipart/form-data", 'form copertina multipart'],
    [$catForm, "minlength=\"12\"", 'password CAT 12 caratteri'],
    [$eventView, "company_website", 'honeypot nel form pubblico'],
    [$eventView, "Programma", 'programma evento'],
    [$indexView, "annullati", 'filtro eventi annullati'],
    [$actions, "campus.event.duplicate", 'duplicazione evento'],
    [$actions, "Content-Type:text/csv", 'export iscrizioni'],
    [$actions, "Accesso Campus CAT approvato", 'notifica approvazione CAT'],
    [$migration, "password_changed_at", 'schema password tracking'],
    [$migration, "chk_events_dates", 'vincolo date evento'],
    [$completeMigration, "waitlist_enabled", 'schema lista attesa'],
];

foreach ($checks as [$haystack,$needle,$label]) {
    if (!str_contains($haystack, $needle)) throw new RuntimeException('Check Campus fallito: ' . $label);
}

fwrite(STDOUT, "Campus hardening smoke OK\n");
