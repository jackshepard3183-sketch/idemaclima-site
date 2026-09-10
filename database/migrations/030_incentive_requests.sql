CREATE TABLE IF NOT EXISTS incentive_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(120) NOT NULL,last_name VARCHAR(120) NOT NULL,company VARCHAR(190) NULL,
  region VARCHAR(120) NOT NULL,province VARCHAR(8) NOT NULL,city VARCHAR(120) NOT NULL,postal_code VARCHAR(12) NOT NULL,
  phone VARCHAR(50) NOT NULL,email VARCHAR(190) NOT NULL,professional_role VARCHAR(80) NOT NULL,
  status ENUM('new','in_progress','closed','spam') NOT NULL DEFAULT 'new',admin_notes TEXT NULL,
  privacy_accepted_at DATETIME NOT NULL,reviewed_at DATETIME NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_incentive_status(status,created_at),KEY idx_incentive_email(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
