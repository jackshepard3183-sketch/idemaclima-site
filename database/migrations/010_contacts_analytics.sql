CREATE TABLE contact_submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(120) NOT NULL,
  last_name VARCHAR(120) NOT NULL,
  region VARCHAR(120) NOT NULL,
  province VARCHAR(8) NOT NULL,
  city VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(50) NULL,
  subject VARCHAR(220) NOT NULL,
  message TEXT NOT NULL,
  attachment_path VARCHAR(500) NULL,
  attachment_name VARCHAR(255) NULL,
  attachment_mime VARCHAR(120) NULL,
  status ENUM('new','in_progress','closed','spam') NOT NULL DEFAULT 'new',
  admin_notes TEXT NULL,
  privacy_accepted_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_contact_status_created (status, created_at),
  KEY idx_contact_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('view','download') NOT NULL,
  source_path VARCHAR(500) NULL,
  visitor_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_document_events_document FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
  KEY idx_document_events_document_date (document_id, created_at),
  KEY idx_document_events_type_date (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE analytics_settings (
  id TINYINT UNSIGNED PRIMARY KEY DEFAULT 1,
  ga4_measurement_id VARCHAR(40) NULL,
  ga4_property_id VARCHAR(40) NULL,
  analytics_enabled TINYINT(1) NOT NULL DEFAULT 0,
  pdf_tracking_enabled TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO analytics_settings (id, analytics_enabled, pdf_tracking_enabled)
VALUES (1, 0, 1)
ON DUPLICATE KEY UPDATE id = id;
