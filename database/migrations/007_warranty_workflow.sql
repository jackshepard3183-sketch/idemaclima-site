ALTER TABLE warranty_registrations
  ADD COLUMN warranty_rule_id BIGINT UNSIGNED NULL AFTER model_id,
  ADD COLUMN warranty_years SMALLINT UNSIGNED NULL AFTER warranty_rule_id,
  ADD COLUMN extension_formula VARCHAR(60) NULL AFTER warranty_years,
  ADD COLUMN registration_days_limit SMALLINT UNSIGNED NULL AFTER extension_formula,
  ADD COLUMN admin_notes TEXT NULL AFTER status,
  ADD CONSTRAINT fk_warranty_registration_rule
    FOREIGN KEY (warranty_rule_id) REFERENCES warranty_rules(id) ON DELETE SET NULL;

CREATE INDEX idx_warranty_registrations_status_created
  ON warranty_registrations (status, created_at);

CREATE INDEX idx_warranty_registrations_email
  ON warranty_registrations (email);

CREATE INDEX idx_warranty_rules_lookup
  ON warranty_rules (model_id, product_id, enabled, valid_from, valid_to);
