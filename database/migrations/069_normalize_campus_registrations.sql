-- Uniforma l'archivio Campus: testi anagrafici maiuscoli, email minuscole e telefoni compatti.
UPDATE event_registrations SET
    first_name=UPPER(TRIM(first_name)),
    last_name=UPPER(TRIM(last_name)),
    email=LOWER(TRIM(email)),
    phone=NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(TRIM(COALESCE(phone,'')),' ',''),'-',''),'(',''),')',''),'.',''),''),
    company=NULLIF(UPPER(TRIM(COALESCE(company,''))),''),
    role=NULLIF(UPPER(TRIM(COALESCE(role,''))),''),
    notes=NULLIF(UPPER(TRIM(COALESCE(notes,''))),'');
