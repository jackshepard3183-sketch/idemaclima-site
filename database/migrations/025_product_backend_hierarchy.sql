ALTER TABLE product_categories
    ADD COLUMN content_status ENUM('draft','published','hidden') NOT NULL DEFAULT 'published' AFTER sort_order;

ALTER TABLE products
    ADD COLUMN content_status ENUM('draft','published','hidden') NOT NULL DEFAULT 'published' AFTER status;

ALTER TABLE product_models
    ADD COLUMN content_status ENUM('draft','published','hidden') NOT NULL DEFAULT 'published' AFTER name;

UPDATE product_categories SET content_status = IF(published = 1, 'published', 'hidden');
UPDATE products SET content_status = IF(published = 1, 'published', 'hidden');
UPDATE product_models SET content_status = IF(published = 1, 'published', 'hidden');

CREATE TABLE IF NOT EXISTS product_features (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    label VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_features_order (product_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_specifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    specification_key VARCHAR(160) NOT NULL,
    specification_value VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_specifications_order (product_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_accessories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(100) NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    published TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product_accessories_order (product_id, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
