ALTER TABLE event_registrations
  ADD COLUMN certificate_number VARCHAR(80) NULL AFTER attended,
  ADD COLUMN certificate_issued_at DATETIME NULL AFTER certificate_number,
  ADD KEY idx_event_registrations_certificate (certificate_number);
