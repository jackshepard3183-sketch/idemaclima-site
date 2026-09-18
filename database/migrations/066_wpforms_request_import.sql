ALTER TABLE contact_submissions
  ADD COLUMN source_wpforms_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN import_review_warning VARCHAR(1000) NULL AFTER admin_notes,
  ADD COLUMN imported_at DATETIME NULL AFTER import_review_warning,
  ADD UNIQUE KEY uq_contact_source_wpforms (source_wpforms_id);

ALTER TABLE incentive_requests
  ADD COLUMN source_wpforms_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN import_review_warning VARCHAR(1000) NULL AFTER admin_notes,
  ADD COLUMN imported_at DATETIME NULL AFTER import_review_warning,
  ADD UNIQUE KEY uq_incentive_source_wpforms (source_wpforms_id);
