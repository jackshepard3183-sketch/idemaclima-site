ALTER TABLE analytics_settings
  ADD COLUMN gtm_container_id VARCHAR(32) NULL AFTER ga4_property_id,
  ADD COLUMN search_console_verification VARCHAR(255) NULL AFTER gtm_container_id,
  ADD COLUMN meta_pixel_id VARCHAR(32) NULL AFTER search_console_verification,
  ADD COLUMN iubenda_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER meta_pixel_id,
  ADD COLUMN iubenda_site_id VARCHAR(32) NULL AFTER iubenda_enabled,
  ADD COLUMN iubenda_cookie_policy_id VARCHAR(32) NOT NULL DEFAULT '38092343' AFTER iubenda_site_id;
