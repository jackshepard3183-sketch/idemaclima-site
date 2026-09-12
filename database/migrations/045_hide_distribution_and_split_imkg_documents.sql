-- Nasconde Distribuzione aria dalle Schede tecniche e separa i documenti IMKG-V B/C.
-- I documenti restano archiviati e disponibili nelle sezioni dedicate.

UPDATE product_categories
SET published = 0
WHERE slug IN (
  'distribuzione-aria',
  'distribuzione-aria--sistemi-e-componenti'
);

UPDATE products p
JOIN product_categories c ON c.id = p.category_id
SET p.published = 0
WHERE c.slug = 'distribuzione-aria--sistemi-e-componenti';

-- IMKG-V (B): elimina la scheda tecnica e le tabelle rese della variante C.
DELETE dl
FROM document_links dl
JOIN products p ON p.id = dl.product_id
JOIN documents d ON d.id = dl.document_id
WHERE p.slug = 'terminali-idronici--imkg-v-b'
  AND d.filename IN (
    'FC_UI_PARETE_IMKG-V-C.pdf',
    'TAB_UI_IMKG-V300C.pdf',
    'TAB_UI_IMKG-V400C.pdf',
    'TAB_UI_IMKG-V600C.pdf'
  );

-- IMKG-V (C): elimina la scheda tecnica e le tabelle rese della variante B.
DELETE dl
FROM document_links dl
JOIN products p ON p.id = dl.product_id
JOIN documents d ON d.id = dl.document_id
WHERE p.slug = 'terminali-idronici--imkg-v-c'
  AND d.filename IN (
    'FC_UI_PARETE_IMKG-V-B.pdf',
    'TAB_UI_IMKG-V250B.pdf',
    'TAB_UI_IMKG-V300B.pdf',
    'TAB_UI_IMKG-V400B.pdf',
    'TAB_UI_IMKG-V500B.pdf',
    'TAB_UI_IMKG-V600B.pdf'
  );
