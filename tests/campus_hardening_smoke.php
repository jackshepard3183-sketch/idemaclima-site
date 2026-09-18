<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$admin = file_get_contents($root . '/app/Controllers/Admin/CampusController.php');
$public = file_get_contents($root . '/app/Controllers/Public/CampusController.php');
$auth = file_get_contents($root . '/app/Auth/CatAuth.php');
$eventForm = file_get_contents($root . '/app/Views/admin/campus_event_form.php');
$eventsAdminView = file_get_contents($root . '/app/Views/admin/campus_events.php');
$routes = file_get_contents($root . '/public/index.php');
$catForm = file_get_contents($root . '/app/Views/admin/cat_user_form.php');
$eventView = file_get_contents($root . '/app/Views/public/campus/event.php');
$migration = file_get_contents($root . '/database/migrations/019_campus_security.sql');
$completeMigration = file_get_contents($root . '/database/migrations/027_campus_complete.sql');
$eventsMigration = file_get_contents($root . '/database/migrations/028_seed_campus_events.sql');
$actions = file_get_contents($root . '/app/Controllers/Admin/CampusActionsController.php');
$indexView = file_get_contents($root . '/app/Views/public/campus/index.php');
$catIndexView = file_get_contents($root . '/app/Views/public/campus/cat_index.php');
$openEventsView = file_get_contents($root . '/app/Views/public/campus/open_events.php');
$registrationsView = file_get_contents($root . '/app/Views/admin/campus_registrations.php');

foreach ([$admin,$public,$auth,$eventForm,$eventsAdminView,$routes,$catForm,$eventView,$migration,$completeMigration,$eventsMigration,$actions,$indexView,$catIndexView,$openEventsView,$registrationsView] as $content) {
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
    [$actions, "campus.event.delete", 'audit eliminazione evento'],
    [$actions, "DELETE FROM events WHERE id=?", 'eliminazione evento'],
    [$actions, "registrations_deleted", 'conteggio iscrizioni eliminate'],
    [$actions, "Upload::removeManaged", 'rimozione copertina evento eliminato'],
    [$eventsAdminView, "/admin/campus/events/delete", 'azione elimina nella tabella eventi'],
    [$eventsAdminView, "L’operazione non può essere annullata", 'conferma eliminazione definitiva'],
    [$routes, "/admin/campus/events/delete", 'rotta eliminazione evento'],
    [$actions, "Upload::duplicateManaged", 'copertina evento duplicata in modo indipendente'],
    [$actions, "r.status=?", 'export iscrizioni filtrabile per stato'],
    [$actions, "Content-Type:text/csv", 'export iscrizioni'],
    [$actions, "Accesso Campus CAT approvato", 'notifica approvazione CAT'],
    [$migration, "password_changed_at", 'schema password tracking'],
    [$migration, "chk_events_dates", 'vincolo date evento'],
    [$completeMigration, "waitlist_enabled", 'schema lista attesa'],
    [$eventsMigration, "pdc-idronici-residenziali-acs-pre-vendita-14-11-2025", 'primo evento Lovable'],
    [$eventsMigration, "sistemi-pdc-idronici-vertemate-30-04-2026", 'ultimo evento Lovable'],
    [$eventsMigration, "ON DUPLICATE KEY UPDATE", 'import eventi idempotente'],
    [$eventsMigration, "registration_open = VALUES(registration_open)", 'iscrizioni storiche chiuse'],
    [$catIndexView, "Storico attività", 'storico attività CAT separato'],
    [$catIndexView, "campus-eventi-cat.webp.php", 'cover predefinita Eventi CAT'],
    [$catIndexView, "event-date-badge", 'badge data Eventi CAT'],
    [$openEventsView, "campus-eventi-aperti.webp.php", 'cover predefinita Eventi aperti'],
    [$admin, "defaultCover", 'cover automatica per tipologia'],
    [$registrationsView, "Tutti gli stati", 'filtro stato iscrizioni'],
];

foreach ($checks as [$haystack,$needle,$label]) {
    if (!str_contains($haystack, $needle)) throw new RuntimeException('Check Campus fallito: ' . $label);
}

fwrite(STDOUT, "Campus hardening smoke OK\n");
