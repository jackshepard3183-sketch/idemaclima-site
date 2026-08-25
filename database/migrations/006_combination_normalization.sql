ALTER TABLE product_combinations
  ADD COLUMN source_product_id BIGINT UNSIGNED NULL AFTER id,
  ADD COLUMN source_label VARCHAR(255) NULL AFTER name,
  ADD COLUMN source_key VARCHAR(255) NULL AFTER slug,
  ADD COLUMN normalization_status ENUM('resolved','partial','pending','review') NOT NULL DEFAULT 'pending' AFTER source_key,
  ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER published,
  ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at,
  ADD UNIQUE KEY uq_product_combinations_source_key (source_key),
  ADD CONSTRAINT fk_product_combinations_source_product
    FOREIGN KEY (source_product_id) REFERENCES products(id) ON DELETE SET NULL;

ALTER TABLE product_combination_items
  MODIFY COLUMN model_id BIGINT UNSIGNED NULL,
  ADD COLUMN product_id BIGINT UNSIGNED NULL AFTER combination_id,
  ADD COLUMN raw_code VARCHAR(180) NULL AFTER model_id,
  ADD COLUMN resolution_status ENUM('resolved_product','resolved_model','pending','review') NOT NULL DEFAULT 'pending' AFTER raw_code,
  ADD COLUMN sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER quantity,
  ADD CONSTRAINT fk_combo_items_product
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  ADD CONSTRAINT chk_combo_item_target
    CHECK (product_id IS NOT NULL OR model_id IS NOT NULL OR raw_code IS NOT NULL);

CREATE TABLE product_combination_documents (
  combination_id BIGINT UNSIGNED NOT NULL,
  document_id BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (combination_id, document_id),
  CONSTRAINT fk_combo_documents_combo
    FOREIGN KEY (combination_id) REFERENCES product_combinations(id) ON DELETE CASCADE,
  CONSTRAINT fk_combo_documents_document
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
