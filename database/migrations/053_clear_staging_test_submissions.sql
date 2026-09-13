-- Pulizia definitiva dei dati di collaudo richiesta dall'amministratore.
-- Restano invariati account amministratori, eventi, contenuti e configurazioni.
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM warranty_generated_certificates;
DELETE FROM warranty_units;
DELETE FROM warranty_registration_details;
DELETE FROM warranty_registrations;
DELETE FROM event_registrations;
DELETE FROM incentive_requests;
DELETE FROM contact_submissions;
DELETE FROM cat_accounts;
SET FOREIGN_KEY_CHECKS=1;
