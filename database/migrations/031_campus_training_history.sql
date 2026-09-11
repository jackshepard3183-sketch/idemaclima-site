ALTER TABLE event_registrations
  ADD COLUMN certificate_number VARCHAR(80) NULL AFTER attended,
  ADD COLUMN certificate_issued_at DATETIME NULL AFTER certificate_number;
