ALTER TABLE warranty_registrations
  ADD COLUMN invoice_required_snapshot TINYINT(1) NULL AFTER registration_days_limit,
  ADD COLUMN fgas_required_snapshot TINYINT(1) NULL AFTER invoice_required_snapshot,
  ADD COLUMN reviewed_at DATETIME NULL AFTER admin_notes;

CREATE INDEX idx_warranty_registrations_certificate
  ON warranty_registrations (certificate_number);
