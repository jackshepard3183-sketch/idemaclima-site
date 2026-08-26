ALTER TABLE catalogs
  ADD COLUMN document_id BIGINT UNSIGNED NULL AFTER cover_image,
  MODIFY COLUMN pdf_path VARCHAR(500) NULL,
  ADD CONSTRAINT fk_catalog_document
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
  ADD KEY idx_catalogs_document (document_id),
  ADD CONSTRAINT chk_catalog_target
    CHECK (document_id IS NOT NULL OR pdf_path IS NOT NULL);
