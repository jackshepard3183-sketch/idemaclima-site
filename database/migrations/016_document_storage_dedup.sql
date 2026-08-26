ALTER TABLE documents
  ADD COLUMN sha256 CHAR(64) NULL AFTER file_path,
  ADD COLUMN file_size BIGINT UNSIGNED NULL AFTER sha256,
  ADD COLUMN mime_type VARCHAR(120) NULL AFTER file_size,
  ADD UNIQUE KEY uq_documents_sha256 (sha256);

CREATE TABLE document_source_aliases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  source_url VARCHAR(1000) NOT NULL,
  source_kind ENUM('wordpress','lovable','legacy','other') NOT NULL DEFAULT 'legacy',
  source_page VARCHAR(1000) NULL,
  first_seen_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_document_source_url (source_url(500)),
  KEY idx_document_source_document (document_id),
  CONSTRAINT fk_document_source_alias_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
