CREATE TABLE assistance_resources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section ENUM('warranty','error_code') NOT NULL,
  label VARCHAR(220) NOT NULL,
  document_id BIGINT UNSIGNED NULL,
  external_url VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (document_id IS NOT NULL OR external_url IS NOT NULL),
  CONSTRAINT fk_assistance_resource_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
  KEY idx_assistance_resources_section (section, published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
