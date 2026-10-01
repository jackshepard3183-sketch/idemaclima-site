-- Correzione dei tre badge della serie IGC-V12-14-16W/D2RN1.
-- Titolo serie e documentazione tecnica riportano D2RN1.
UPDATE products
SET badges_text = REPLACE(REPLACE(REPLACE(badges_text,
    'IGC-V12W/DR2N1', 'IGC-V12W/D2RN1'),
    'IGC-V14W/DR2N1', 'IGC-V14W/D2RN1'),
    'IGC-V16W/DR2N1', 'IGC-V16W/D2RN1')
WHERE name = 'IGC-V12-14-16W/D2RN1';

UPDATE product_models m
JOIN products p ON p.id = m.product_id
SET m.code = REPLACE(m.code, '/DR2N1', '/D2RN1')
WHERE p.name = 'IGC-V12-14-16W/D2RN1'
  AND m.code IN ('IGC-V12W/DR2N1', 'IGC-V14W/DR2N1', 'IGC-V16W/DR2N1');
