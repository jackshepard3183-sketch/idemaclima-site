ALTER TABLE cat_accounts
  ADD COLUMN password_changed_at DATETIME NULL AFTER password_hash,
  ADD COLUMN disabled_at DATETIME NULL AFTER active,
  ADD KEY idx_cat_accounts_active_verified (active, verified_at);

ALTER TABLE events
  ADD KEY idx_events_publication (published, cancelled, audience, starts_at),
  ADD CONSTRAINT chk_events_dates CHECK (ends_at IS NULL OR ends_at >= starts_at),
  ADD CONSTRAINT chk_events_deadline CHECK (registration_deadline IS NULL OR registration_deadline <= starts_at);

ALTER TABLE event_registrations
  ADD KEY idx_event_registrations_cat (cat_account_id, event_id),
  ADD KEY idx_event_registrations_email_status (email, status);
