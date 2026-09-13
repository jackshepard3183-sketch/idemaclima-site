-- Uniforma i nomi completi delle province in tutti i moduli.
ALTER TABLE incentive_requests
  MODIFY COLUMN province VARCHAR(120) NOT NULL;

ALTER TABLE warranty_registrations
  MODIFY COLUMN province VARCHAR(120) NOT NULL;
