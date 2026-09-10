ALTER TABLE event_registrations
  ADD COLUMN IF NOT EXISTS certificate_number VARCHAR(80) NULL AFTER attended,
  ADD COLUMN IF NOT EXISTS certificate_issued_at DATETIME NULL AFTER certificate_number;
