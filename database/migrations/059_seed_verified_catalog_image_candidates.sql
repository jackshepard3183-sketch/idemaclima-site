UPDATE product_image_reviews r
JOIN products p ON p.id=r.product_id
SET r.review_status='recovered',
    r.candidate_path='/product-image-candidates/icz-r32.webp',
    r.candidate_width=600,
    r.candidate_height=600,
    r.source_catalog='Catalogo Generale IDEMA 2026',
    r.source_page=60,
    r.notes='Render estratto dal catalogo e verificato visivamente rispetto all’immagine attuale.',
    r.reviewed_at=CURRENT_TIMESTAMP
WHERE p.name='ICZ-R32' AND r.protected=0 AND r.review_status<>'approved';

UPDATE product_image_reviews r
JOIN products p ON p.id=r.product_id
SET r.review_status='recovered',
    r.candidate_path='/product-image-candidates/itxi-r32.webp',
    r.candidate_width=600,
    r.candidate_height=600,
    r.source_catalog='Catalogo Multi Pro IDEMA 2026',
    r.source_page=16,
    r.notes='Render estratto dal catalogo e verificato visivamente rispetto all’immagine attuale.',
    r.reviewed_at=CURRENT_TIMESTAMP
WHERE p.name='ITXI-R32' AND r.protected=0 AND r.review_status<>'approved';

UPDATE product_image_reviews r
JOIN products p ON p.id=r.product_id
SET r.review_status='recovered',
    r.candidate_path='/product-image-candidates/imihq4cn18.webp',
    r.candidate_width=600,
    r.candidate_height=600,
    r.source_catalog='Catalogo VRF V8 IDEMA 2026',
    r.source_page=48,
    r.notes='Render estratto dal catalogo e verificato visivamente rispetto all’immagine attuale.',
    r.reviewed_at=CURRENT_TIMESTAMP
WHERE p.name='IMIHQ4CN18' AND r.protected=0 AND r.review_status<>'approved';

UPDATE product_image_reviews r
JOIN products p ON p.id=r.product_id
SET r.review_status='recovered',
    r.candidate_path='/product-image-candidates/iszz-r32-storico.webp',
    r.candidate_width=600,
    r.candidate_height=600,
    r.source_catalog='Catalogo Multi Pro IDEMA 2026',
    r.source_page=9,
    r.notes='Render della famiglia storica estratto dal catalogo e verificato visivamente. Controllare il modello prima dell’approvazione.',
    r.reviewed_at=CURRENT_TIMESTAMP
WHERE p.name='ISZ(Z)-R32' AND r.protected=0 AND r.review_status<>'approved';
