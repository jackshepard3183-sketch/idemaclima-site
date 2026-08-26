CREATE TABLE request_rate_limits (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  action_key VARCHAR(80) NOT NULL,
  client_hash CHAR(64) NOT NULL,
  window_start DATETIME NOT NULL,
  hit_count INT UNSIGNED NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rate_limit_window (action_key, client_hash, window_start),
  KEY idx_rate_limit_cleanup (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
