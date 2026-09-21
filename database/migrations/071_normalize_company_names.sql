-- Uniforma esclusivamente i campi Azienda/Ragione sociale già presenti.
UPDATE event_registrations SET company=UPPER(TRIM(company)) WHERE company IS NOT NULL AND company<>'';
UPDATE cat_accounts SET company_name=UPPER(TRIM(company_name)) WHERE company_name<>'';
UPDATE incentive_requests SET company=UPPER(TRIM(company)) WHERE company IS NOT NULL AND company<>'';

-- Correzioni certe già verificate nell'archivio IDEMA.
UPDATE event_registrations SET company='B.P. IMPIANTI SRL' WHERE company IN ('B.P.,IMPIANTI SRL','B.P., IMPIANTI SRL');
UPDATE cat_accounts SET company_name='B.P. IMPIANTI SRL' WHERE company_name IN ('B.P.,IMPIANTI SRL','B.P., IMPIANTI SRL');
UPDATE incentive_requests SET company='B.P. IMPIANTI SRL' WHERE company IN ('B.P.,IMPIANTI SRL','B.P., IMPIANTI SRL');

UPDATE event_registrations SET company='GRILLI RAPPRESENTANZE' WHERE company='GRILLIRAPORESENTANZE';
UPDATE cat_accounts SET company_name='GRILLI RAPPRESENTANZE' WHERE company_name='GRILLIRAPORESENTANZE';
UPDATE incentive_requests SET company='GRILLI RAPPRESENTANZE' WHERE company='GRILLIRAPORESENTANZE';

UPDATE event_registrations SET company='GIANNI BENVENUTO S.P.A.' WHERE company='GIANNI BNVENUTO S.P.A.';
UPDATE cat_accounts SET company_name='GIANNI BENVENUTO S.P.A.' WHERE company_name='GIANNI BNVENUTO S.P.A.';
UPDATE incentive_requests SET company='GIANNI BENVENUTO S.P.A.' WHERE company='GIANNI BNVENUTO S.P.A.';

UPDATE event_registrations SET company='LMF IMPIANTI' WHERE company='LFM IMPIANTI';
UPDATE cat_accounts SET company_name='LMF IMPIANTI' WHERE company_name='LFM IMPIANTI';
UPDATE incentive_requests SET company='LMF IMPIANTI' WHERE company='LFM IMPIANTI';

UPDATE event_registrations SET company='TEDI S.R.L.S.' WHERE company IN ('TEDI S.L.S','TEDI S.L.S.','TEDI S.R.L.S');
UPDATE cat_accounts SET company_name='TEDI S.R.L.S.' WHERE company_name IN ('TEDI S.L.S','TEDI S.L.S.','TEDI S.R.L.S');
UPDATE incentive_requests SET company='TEDI S.R.L.S.' WHERE company IN ('TEDI S.L.S','TEDI S.L.S.','TEDI S.R.L.S');

UPDATE event_registrations SET company='ENERG.ON S.R.L.' WHERE company IN ('ENERG.ON','ENERG-ON','ENERG. ON','ENERGON','EMERG-ON','ENERG.ON SRL','ENERG. ON SRL');
UPDATE cat_accounts SET company_name='ENERG.ON S.R.L.' WHERE company_name IN ('ENERG.ON','ENERG-ON','ENERG. ON','ENERGON','EMERG-ON','ENERG.ON SRL','ENERG. ON SRL');
UPDATE incentive_requests SET company='ENERG.ON S.R.L.' WHERE company IN ('ENERG.ON','ENERG-ON','ENERG. ON','ENERGON','EMERG-ON','ENERG.ON SRL','ENERG. ON SRL');

-- Forme societarie: varianti senza punteggiatura o senza il punto finale.
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-4),'S.R.L.S.') WHERE company LIKE '% SRLS';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-4),'S.R.L.S.') WHERE company_name LIKE '% SRLS';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-4),'S.R.L.S.') WHERE company LIKE '% SRLS';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-7),'S.R.L.S.') WHERE company LIKE '% S.R.L.S';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-7),'S.R.L.S.') WHERE company_name LIKE '% S.R.L.S';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-7),'S.R.L.S.') WHERE company LIKE '% S.R.L.S';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-7),'S.R.L.S.') WHERE company LIKE '% S R L S';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-7),'S.R.L.S.') WHERE company_name LIKE '% S R L S';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-7),'S.R.L.S.') WHERE company LIKE '% S R L S';

UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.R.L.') WHERE company LIKE '% SRL';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-3),'S.R.L.') WHERE company_name LIKE '% SRL';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.R.L.') WHERE company LIKE '% SRL';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.R.L.') WHERE company LIKE '% S.R.L';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-5),'S.R.L.') WHERE company_name LIKE '% S.R.L';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.R.L.') WHERE company LIKE '% S.R.L';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.R.L.') WHERE company LIKE '% S R L';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-5),'S.R.L.') WHERE company_name LIKE '% S R L';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.R.L.') WHERE company LIKE '% S R L';

UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.N.C.') WHERE company LIKE '% SNC';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-3),'S.N.C.') WHERE company_name LIKE '% SNC';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.N.C.') WHERE company LIKE '% SNC';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.N.C.') WHERE company LIKE '% S.N.C';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-5),'S.N.C.') WHERE company_name LIKE '% S.N.C';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.N.C.') WHERE company LIKE '% S.N.C';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.N.C.') WHERE company LIKE '% S N C';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-5),'S.N.C.') WHERE company_name LIKE '% S N C';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.N.C.') WHERE company LIKE '% S N C';

UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.A.S.') WHERE company LIKE '% SAS';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-3),'S.A.S.') WHERE company_name LIKE '% SAS';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.A.S.') WHERE company LIKE '% SAS';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.A.S.') WHERE company LIKE '% S.A.S';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-5),'S.A.S.') WHERE company_name LIKE '% S.A.S';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.A.S.') WHERE company LIKE '% S.A.S';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.A.S.') WHERE company LIKE '% S A S';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-5),'S.A.S.') WHERE company_name LIKE '% S A S';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.A.S.') WHERE company LIKE '% S A S';

UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.P.A.') WHERE company LIKE '% SPA';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-3),'S.P.A.') WHERE company_name LIKE '% SPA';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.P.A.') WHERE company LIKE '% SPA';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.P.A.') WHERE company LIKE '% S.P.A';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-5),'S.P.A.') WHERE company_name LIKE '% S.P.A';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.P.A.') WHERE company LIKE '% S.P.A';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.P.A.') WHERE company LIKE '% S P A';
UPDATE cat_accounts SET company_name=CONCAT(LEFT(company_name,CHAR_LENGTH(company_name)-5),'S.P.A.') WHERE company_name LIKE '% S P A';
UPDATE incentive_requests SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-5),'S.P.A.') WHERE company LIKE '% S P A';
