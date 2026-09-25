-- Riallinea solo le pratiche WPForms ancora in attesa e senza certificato generato.
-- I modelli esterni corrispondono esattamente ai prodotti esistenti e restano in bozza.
UPDATE warranty_registrations wr
JOIN warranty_registration_details d ON d.registration_id=wr.id
JOIN products p ON p.name=d.outer_unit AND p.name IN ('2MIT-50-R32','3MIT-78-R32','2MWTZ-50-R32','3MWTZ-70-R32')
JOIN product_models pm ON pm.product_id=p.id AND pm.code=d.outer_unit
JOIN warranty_rules rule ON rule.product_id=p.id AND rule.model_id IS NULL AND rule.enabled=1
  AND (rule.valid_from IS NULL OR rule.valid_from<=COALESCE(wr.invoice_date,CURRENT_DATE))
  AND (rule.valid_to IS NULL OR rule.valid_to>=COALESCE(wr.invoice_date,CURRENT_DATE))
LEFT JOIN warranty_generated_certificates cert ON cert.registration_id=wr.id
SET wr.model_id=pm.id,wr.warranty_rule_id=rule.id,wr.warranty_years=rule.warranty_years,
    wr.extension_formula=rule.extension_formula,wr.registration_days_limit=rule.registration_days_limit,
    wr.invoice_required_snapshot=rule.invoice_required,wr.fgas_required_snapshot=rule.fgas_required
WHERE wr.source_wpforms_id IS NOT NULL AND wr.status='pending' AND cert.id IS NULL
  AND LOWER(d.product_type) IN ('multi','multi split');

UPDATE warranty_units u
JOIN warranty_registrations wr ON wr.id=u.registration_id
JOIN warranty_registration_details d ON d.registration_id=wr.id
JOIN product_models pm ON pm.id=wr.model_id AND pm.code=d.outer_unit
LEFT JOIN warranty_generated_certificates cert ON cert.registration_id=wr.id
SET u.model_id=wr.model_id
WHERE wr.source_wpforms_id IS NOT NULL AND wr.status='pending' AND cert.id IS NULL
  AND u.unit_type='outdoor' AND d.outer_unit IN ('2MIT-50-R32','3MIT-78-R32','2MWTZ-50-R32','3MWTZ-70-R32');

-- Le due sigle storiche non hanno una regola verificata sul nuovo sito.
UPDATE warranty_registrations wr
JOIN warranty_registration_details d ON d.registration_id=wr.id
LEFT JOIN warranty_generated_certificates cert ON cert.registration_id=wr.id
SET wr.warranty_rule_id=NULL,wr.warranty_years=NULL,wr.extension_formula=NULL,
    wr.registration_days_limit=NULL,
    wr.import_review_warning=CONCAT_WS('; ',NULLIF(wr.import_review_warning,''),
      'Modello esterno storico senza regola verificata: controllare prima di approvare')
WHERE wr.source_wpforms_id IS NOT NULL AND wr.status='pending' AND cert.id IS NULL
  AND LOWER(d.product_type) IN ('multi','multi split')
  AND d.outer_unit IN ('2MW-50-R32','3MW-70-R32');

-- Ripristina la durata delle pratiche Mono secondo la regola associata al modello.
UPDATE warranty_registrations wr
JOIN warranty_registration_details d ON d.registration_id=wr.id
JOIN product_models pm ON pm.id=wr.model_id
JOIN warranty_rules rule ON rule.product_id=pm.product_id AND rule.model_id IS NULL AND rule.enabled=1
  AND (rule.valid_from IS NULL OR rule.valid_from<=COALESCE(wr.invoice_date,CURRENT_DATE))
  AND (rule.valid_to IS NULL OR rule.valid_to>=COALESCE(wr.invoice_date,CURRENT_DATE))
LEFT JOIN warranty_generated_certificates cert ON cert.registration_id=wr.id
SET wr.warranty_rule_id=rule.id,wr.warranty_years=rule.warranty_years,
    wr.extension_formula=rule.extension_formula,wr.registration_days_limit=rule.registration_days_limit,
    wr.invoice_required_snapshot=rule.invoice_required,wr.fgas_required_snapshot=rule.fgas_required
WHERE wr.source_wpforms_id IS NOT NULL AND wr.status='pending' AND cert.id IS NULL
  AND LOWER(d.product_type) IN ('mono','mono split');

UPDATE warranty_registration_details d
JOIN warranty_registrations wr ON wr.id=d.registration_id
LEFT JOIN warranty_generated_certificates cert ON cert.registration_id=wr.id
SET d.combination=CASE
      WHEN UPPER(d.combination) LIKE CONCAT(UPPER(d.outer_unit),' + %')
      THEN SUBSTRING(d.combination,CHAR_LENGTH(d.outer_unit)+4)
      ELSE d.combination END,
    d.product_type='multi'
WHERE wr.source_wpforms_id IS NOT NULL AND wr.status='pending' AND cert.id IS NULL
  AND LOWER(d.product_type) IN ('multi','multi split');

UPDATE warranty_registration_details d
JOIN warranty_registrations wr ON wr.id=d.registration_id
LEFT JOIN warranty_generated_certificates cert ON cert.registration_id=wr.id
SET d.product_type='mono'
WHERE wr.source_wpforms_id IS NOT NULL AND wr.status='pending' AND cert.id IS NULL
  AND LOWER(d.product_type)='mono split';
