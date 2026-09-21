-- Accorpa i duplicati WordPress confermati mantenendo un'iscrizione per persona e corso.
DELETE FROM event_registrations
WHERE source_wordpress_id IN (10793,10837,11022,11023,11024,11025,11164,11194);

UPDATE event_registrations
SET notes=NULL
WHERE source_wordpress_id IN (10792,10836,11021,11163,11192)
  AND UPPER(COALESCE(notes,'')) LIKE 'POSSIBILE DUPLICATO%';

-- Correzioni certe rilevate nell'archivio importato.
UPDATE event_registrations SET company='B.P. IMPIANTI S.R.L.' WHERE company='B.P.,IMPIANTI SRL';
UPDATE event_registrations SET company='TEDI S.R.L.S.' WHERE company IN ('TEDI S.R.L.S','TEDI S.L.S');
UPDATE event_registrations SET company='GRILLI RAPPRESENTANZE' WHERE company='GRILLIRAPORESENTANZE';
UPDATE event_registrations SET company='GIANNI BENVENUTO S.P.A.' WHERE company='GIANNI BNVENUTO S.P.A.';
UPDATE event_registrations SET company='LMF IMPIANTI' WHERE company='LFM IMPIANTI';
UPDATE event_registrations SET company='ENERG.ON S.R.L.' WHERE company IN ('ENERG.ON SRL','ENERG.ON','ENERG-ON','ENERG. ON SRL','ENERGON','EMERG-ON');
UPDATE event_registrations SET first_name='EMANUELE' WHERE first_name='EMAUELE' AND last_name='ANDREELLO';
UPDATE event_registrations SET first_name='FABRIZIO' WHERE first_name='FABRIIO' AND last_name='GRILLI';
UPDATE event_registrations SET last_name='GIOFRÈ' WHERE last_name='GIOFRĖ';

-- Uniforma le principali forme societarie quando sono scritte senza punteggiatura.
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-4),'S.R.L.S.') WHERE company LIKE '% SRLS';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.R.L.') WHERE company LIKE '% SRL';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.N.C.') WHERE company LIKE '% SNC';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.A.S.') WHERE company LIKE '% SAS';
UPDATE event_registrations SET company=CONCAT(LEFT(company,CHAR_LENGTH(company)-3),'S.P.A.') WHERE company LIKE '% SPA';
