CREATE TABLE assistance_resources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section ENUM('warranty','error_code') NOT NULL,
  label VARCHAR(220) NOT NULL,
  document_id BIGINT UNSIGNED NULL,
  external_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  archived_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT chk_assistance_resource_target CHECK (
    (document_id IS NOT NULL AND external_url IS NULL)
    OR
    (document_id IS NULL AND external_url IS NOT NULL AND CHAR_LENGTH(TRIM(external_url)) > 0)
  ),
  CONSTRAINT fk_assistance_resource_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE RESTRICT,
  KEY idx_assistance_resources_section (section, archived_at, published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
