CREATE TABLE warranty_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NULL,
  model_id BIGINT UNSIGNED NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  warranty_years SMALLINT UNSIGNED NOT NULL,
  extension_formula VARCHAR(60) NULL,
  registration_days_limit SMALLINT UNSIGNED NULL,
  invoice_required TINYINT(1) NOT NULL DEFAULT 1,
  fgas_required TINYINT(1) NOT NULL DEFAULT 1,
  valid_from DATE NULL,
  valid_to DATE NULL,
  notes TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CHECK (product_id IS NOT NULL OR model_id IS NOT NULL),
  CONSTRAINT fk_warranty_rule_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_warranty_rule_model FOREIGN KEY (model_id) REFERENCES product_models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warranty_registrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  certificate_number VARCHAR(80) NULL UNIQUE,
  model_id BIGINT UNSIGNED NOT NULL,
  customer_first_name VARCHAR(120) NOT NULL,
  customer_last_name VARCHAR(120) NOT NULL,
  fiscal_code VARCHAR(32) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  address VARCHAR(255) NOT NULL,
  postal_code VARCHAR(12) NOT NULL,
  city VARCHAR(120) NOT NULL,
  province VARCHAR(8) NOT NULL,
  region VARCHAR(120) NOT NULL,
  invoice_date DATE NOT NULL,
  invoice_file VARCHAR(500) NOT NULL,
  fgas_file VARCHAR(500) NULL,
  privacy_accepted_at DATETIME NOT NULL,
  status ENUM('pending','approved','rejected','issued') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_warranty_registration_model FOREIGN KEY (model_id) REFERENCES product_models(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warranty_units (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id BIGINT UNSIGNED NOT NULL,
  model_id BIGINT UNSIGNED NULL,
  unit_type ENUM('outdoor','indoor','other') NOT NULL,
  serial_number VARCHAR(160) NOT NULL,
  CONSTRAINT fk_warranty_units_registration FOREIGN KEY (registration_id) REFERENCES warranty_registrations(id) ON DELETE CASCADE,
  CONSTRAINT fk_warranty_units_model FOREIGN KEY (model_id) REFERENCES product_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE warranty_certificates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  registration_id BIGINT UNSIGNED NOT NULL UNIQUE,
  certificate_number VARCHAR(80) NOT NULL UNIQUE,
  template_version VARCHAR(80) NULL,
  pdf_path VARCHAR(500) NOT NULL,
  issued_at DATETIME NOT NULL,
  emailed_at DATETIME NULL,
  CONSTRAINT fk_warranty_cert_registration FOREIGN KEY (registration_id) REFERENCES warranty_registrations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
