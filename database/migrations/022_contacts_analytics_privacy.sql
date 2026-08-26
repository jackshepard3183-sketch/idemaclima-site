ALTER TABLE contact_submissions
  ADD COLUMN reviewed_at DATETIME NULL AFTER admin_notes;

ALTER TABLE document_events
  MODIFY COLUMN source_path VARCHAR(255) NULL;

ALTER TABLE analytics_settings
  ADD COLUMN consent_required TINYINT(1) NOT NULL DEFAULT 1 AFTER analytics_enabled,
  ADD COLUMN internal_tracking_retention_days SMALLINT UNSIGNED NOT NULL DEFAULT 180 AFTER pdf_tracking_enabled;
