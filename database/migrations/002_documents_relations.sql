CREATE TABLE document_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  slug VARCHAR(140) NOT NULL UNIQUE,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_type_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  filename VARCHAR(255) NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  revision VARCHAR(80) NULL,
  document_year SMALLINT UNSIGNED NULL,
  published TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_documents_type FOREIGN KEY (document_type_id) REFERENCES document_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NULL,
  product_id BIGINT UNSIGNED NULL,
  model_id BIGINT UNSIGNED NULL,
  CONSTRAINT fk_document_links_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  CONSTRAINT fk_document_links_category FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE CASCADE,
  CONSTRAINT fk_document_links_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_document_links_model FOREIGN KEY (model_id) REFERENCES product_models(id) ON DELETE CASCADE,
  CHECK (category_id IS NOT NULL OR product_id IS NOT NULL OR model_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_compatibilities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  compatible_product_id BIGINT UNSIGNED NOT NULL,
  relation_type VARCHAR(80) NOT NULL DEFAULT 'compatible_with',
  UNIQUE KEY uq_product_compat (product_id, compatible_product_id, relation_type),
  CONSTRAINT fk_compat_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_compat_compatible FOREIGN KEY (compatible_product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_combinations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(220) NOT NULL,
  slug VARCHAR(240) NOT NULL UNIQUE,
  published TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_combination_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  combination_id BIGINT UNSIGNED NOT NULL,
  model_id BIGINT UNSIGNED NOT NULL,
  unit_role ENUM('outdoor_unit','indoor_unit','accessory','other') NOT NULL DEFAULT 'other',
  quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_combination_model_role (combination_id, model_id, unit_role),
  CONSTRAINT fk_combo_items_combo FOREIGN KEY (combination_id) REFERENCES product_combinations(id) ON DELETE CASCADE,
  CONSTRAINT fk_combo_items_model FOREIGN KEY (model_id) REFERENCES product_models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
