-- Il modulo invia la denominazione ufficiale completa della provincia.
ALTER TABLE contact_submissions
  MODIFY COLUMN province VARCHAR(120) NOT NULL;
