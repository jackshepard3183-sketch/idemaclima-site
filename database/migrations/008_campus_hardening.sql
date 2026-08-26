ALTER TABLE events
  ADD COLUMN registration_deadline DATETIME NULL AFTER registration_open,
  ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER cancelled,
  ADD KEY idx_events_audience_date (audience, starts_at);

ALTER TABLE event_registrations
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD UNIQUE KEY uq_event_registration_email (event_id, email),
  ADD KEY idx_event_registrations_status (event_id, status);
