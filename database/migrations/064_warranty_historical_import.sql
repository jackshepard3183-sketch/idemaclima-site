ALTER TABLE warranty_units DROP INDEX uq_warranty_units_serial_number;

ALTER TABLE warranty_registrations
  ADD COLUMN source_wpforms_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN import_review_warning TEXT NULL AFTER admin_notes,
  ADD COLUMN imported_at DATETIME NULL AFTER import_review_warning,
  ADD UNIQUE KEY uq_warranty_registrations_source_wpforms (source_wpforms_id);

CREATE INDEX ix_warranty_units_serial_number ON warranty_units (serial_number);
