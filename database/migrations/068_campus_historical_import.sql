ALTER TABLE events
  MODIFY starts_at DATETIME NULL,
  ADD COLUMN archived TINYINT(1) NOT NULL DEFAULT 0 AFTER cancelled,
  ADD KEY idx_events_archived (archived, audience, starts_at);

ALTER TABLE event_registrations
  DROP INDEX uq_event_registration_email,
  ADD COLUMN source_wordpress_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN legacy_checked_in_at DATETIME NULL AFTER attended,
  ADD UNIQUE KEY uq_event_registration_wordpress (source_wordpress_id),
  ADD KEY idx_event_registration_email (event_id, email);
