-- Uniforma in maiuscolo i dati già registrati dai moduli sullo staging.
UPDATE contact_submissions SET
    first_name=UPPER(first_name),
    last_name=UPPER(last_name),
    region=UPPER(region),
    city=UPPER(city),
    subject=UPPER(subject),
    message=UPPER(message);

UPDATE incentive_requests SET
    first_name=UPPER(first_name),
    last_name=UPPER(last_name),
    company=UPPER(company),
    region=UPPER(region),
    city=UPPER(city),
    professional_role=UPPER(professional_role);

UPDATE warranty_registrations SET
    customer_first_name=UPPER(customer_first_name),
    customer_last_name=UPPER(customer_last_name),
    address=UPPER(address),
    city=UPPER(city),
    region=UPPER(region),
    province=CASE UPPER(TRIM(province))
        WHEN 'ENNA' THEN 'EN'
        WHEN 'TRENTO' THEN 'TN'
        WHEN 'RAGUSA' THEN 'RG'
        WHEN 'BERGAMO' THEN 'BG'
        WHEN 'MILANO' THEN 'MI'
        WHEN 'BARI' THEN 'BA'
        WHEN 'VERONA' THEN 'VR'
        WHEN 'PAVIA' THEN 'PV'
        WHEN 'L''AQUILA' THEN 'AQ'
        WHEN 'MONZA E BRIANZA' THEN 'MB'
        ELSE UPPER(TRIM(province))
    END;

UPDATE event_registrations SET
    first_name=UPPER(first_name),
    last_name=UPPER(last_name),
    company=UPPER(company),
    role=UPPER(role),
    notes=UPPER(notes);

UPDATE cat_accounts SET
    company_name=UPPER(company_name),
    contact_first_name=UPPER(contact_first_name),
    contact_last_name=UPPER(contact_last_name);
