-- Azzeramento definitivo delle compilazioni di prova nello staging IDEMA.
-- Restano invariati amministratori, eventi, configurazioni, contenuti, cataloghi, prodotti e documenti.
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM audit_log WHERE entity_type IN ('warranty_registration','contact_submission','incentive_request','event_registration','cat_account');
DELETE FROM warranty_generated_certificates;
DELETE FROM warranty_units;
DELETE FROM warranty_registration_details;
DELETE FROM warranty_registrations;
DELETE FROM event_registrations;
DELETE FROM incentive_requests;
DELETE FROM contact_submissions;
DELETE FROM cat_accounts;
ALTER TABLE warranty_generated_certificates AUTO_INCREMENT=1;
ALTER TABLE warranty_units AUTO_INCREMENT=1;
ALTER TABLE warranty_registrations AUTO_INCREMENT=1;
ALTER TABLE event_registrations AUTO_INCREMENT=1;
ALTER TABLE incentive_requests AUTO_INCREMENT=1;
ALTER TABLE contact_submissions AUTO_INCREMENT=1;
ALTER TABLE cat_accounts AUTO_INCREMENT=1;
SET FOREIGN_KEY_CHECKS=1;
