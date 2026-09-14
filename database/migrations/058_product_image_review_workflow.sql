CREATE TABLE product_image_reviews (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id BIGINT UNSIGNED NOT NULL,
  review_status ENUM('to_review','insufficient','recovered','missing_catalog','approved') NOT NULL DEFAULT 'to_review',
  protected TINYINT(1) NOT NULL DEFAULT 0,
  original_image_path VARCHAR(1000) NULL,
  candidate_path VARCHAR(1000) NULL,
  candidate_width INT UNSIGNED NULL,
  candidate_height INT UNSIGNED NULL,
  source_catalog VARCHAR(255) NULL,
  source_page INT UNSIGNED NULL,
  notes TEXT NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at TIMESTAMP NULL,
  approved_at TIMESTAMP NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_image_review_product (product_id),
  KEY idx_product_image_review_status (review_status,protected),
  CONSTRAINT fk_product_image_review_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  CONSTRAINT fk_product_image_review_admin FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO product_image_reviews(product_id,review_status,protected)
SELECT id,'to_review',CASE WHEN name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR') THEN 1 ELSE 0 END FROM products;
