-- Conferma dell'equivalenza comunicata: 2MW-50-R32 / 3MW-70-R32
-- condividono combinazioni e regole con 2MWTZ-50-R32 / 3MWTZ-70-R32.
-- Si conserva il codice originale dichiarato nel modulo WPForms.
UPDATE warranty_registrations wr
JOIN warranty_registration_details d ON d.registration_id=wr.id
JOIN product_models pm ON pm.code=CASE d.outer_unit
  WHEN '2MW-50-R32' THEN '2MWTZ-50-R32'
  WHEN '3MW-70-R32' THEN '3MWTZ-70-R32' END
JOIN products p ON p.id=pm.product_id AND p.name=pm.code
JOIN warranty_rules rule ON rule.product_id=p.id AND rule.model_id IS NULL AND rule.enabled=1
  AND (rule.valid_from IS NULL OR rule.valid_from<=COALESCE(wr.invoice_date,CURRENT_DATE))
  AND (rule.valid_to IS NULL OR rule.valid_to>=COALESCE(wr.invoice_date,CURRENT_DATE))
LEFT JOIN warranty_generated_certificates cert ON cert.registration_id=wr.id
SET wr.model_id=pm.id,wr.warranty_rule_id=rule.id,wr.warranty_years=rule.warranty_years,
    wr.extension_formula=rule.extension_formula,wr.registration_days_limit=rule.registration_days_limit,
    wr.invoice_required_snapshot=rule.invoice_required,wr.fgas_required_snapshot=rule.fgas_required,
    wr.import_review_warning=NULLIF(TRIM(BOTH ';' FROM TRIM(REPLACE(
      COALESCE(wr.import_review_warning,''),
      'Modello esterno storico senza regola verificata: controllare prima di approvare',''))),'')
WHERE wr.source_wpforms_id IS NOT NULL AND wr.status='pending' AND cert.id IS NULL
  AND LOWER(d.product_type) IN ('multi','multi split')
  AND d.outer_unit IN ('2MW-50-R32','3MW-70-R32');

UPDATE warranty_units u
JOIN warranty_registrations wr ON wr.id=u.registration_id
JOIN warranty_registration_details d ON d.registration_id=wr.id
JOIN product_models pm ON pm.id=wr.model_id AND pm.code=CASE d.outer_unit
  WHEN '2MW-50-R32' THEN '2MWTZ-50-R32'
  WHEN '3MW-70-R32' THEN '3MWTZ-70-R32' END
LEFT JOIN warranty_generated_certificates cert ON cert.registration_id=wr.id
SET u.model_id=wr.model_id
WHERE wr.source_wpforms_id IS NOT NULL AND wr.status='pending' AND cert.id IS NULL
  AND u.unit_type='outdoor' AND d.outer_unit IN ('2MW-50-R32','3MW-70-R32');
