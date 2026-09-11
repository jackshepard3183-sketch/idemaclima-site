INSERT INTO site_settings (setting_group,setting_key,setting_value,updated_by) VALUES
('email','sender_name','Idema Clima Srl',NULL),
('email','sender_email','no-reply@rappresentanzeguanzirolisas.it',NULL),
('email','contacts_recipients','commerciale.tre@idemaclima.it',NULL),
('email','warranty_recipients','commerciale.tre@idemaclima.it',NULL),
('email','incentives_recipients','commerciale.tre@idemaclima.it',NULL),
('email','campus_recipients','commerciale.tre@idemaclima.it',NULL),
('email','notifications_enabled','1',NULL)
ON DUPLICATE KEY UPDATE setting_value=IF(TRIM(COALESCE(setting_value,''))='',VALUES(setting_value),setting_value);
