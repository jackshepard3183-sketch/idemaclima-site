UPDATE product_image_reviews r JOIN products p ON p.id=r.product_id
SET r.review_status='recovered',r.candidate_path='/product-image-candidates/itz-r32.webp',
    r.candidate_width=600,r.candidate_height=600,r.source_catalog='Catalogo Generale IDEMA 2026',r.source_page=66,
    r.notes='Render estratto dal catalogo e verificato visivamente rispetto all’immagine attuale.',r.reviewed_at=CURRENT_TIMESTAMP
WHERE p.name='ITZ-R32' AND r.protected=0 AND r.review_status<>'approved';

UPDATE product_image_reviews r JOIN products p ON p.id=r.product_id
SET r.review_status='recovered',r.candidate_path='/product-image-candidates/iqzzi-r32.webp',
    r.candidate_width=600,r.candidate_height=600,r.source_catalog='Catalogo Multi Pro IDEMA 2026',r.source_page=12,
    r.notes='Render estratto dal catalogo e verificato visivamente rispetto all’immagine attuale.',r.reviewed_at=CURRENT_TIMESTAMP
WHERE p.name='IQZZI-R32' AND r.protected=0 AND r.review_status<>'approved';

UPDATE product_image_reviews r JOIN products p ON p.id=r.product_id
SET r.review_status='recovered',r.candidate_path='/product-image-candidates/imi2-q4cdn1.webp',
    r.candidate_width=600,r.candidate_height=600,r.source_catalog='Catalogo Atomix R32 IDEMA 2025',r.source_page=10,
    r.notes='Render estratto dal catalogo e verificato visivamente rispetto all’immagine attuale.',r.reviewed_at=CURRENT_TIMESTAMP
WHERE p.name='IMI2-Q4CDN1' AND r.protected=0 AND r.review_status<>'approved';

UPDATE product_image_reviews r JOIN products p ON p.id=r.product_id
SET r.review_status='recovered',r.candidate_path='/product-image-candidates/ifzi-r32.webp',
    r.candidate_width=600,r.candidate_height=600,r.source_catalog='Catalogo Multi Pro IDEMA 2026',r.source_page=13,
    r.notes='Render estratto dal catalogo e verificato visivamente rispetto all’immagine attuale.',r.reviewed_at=CURRENT_TIMESTAMP
WHERE p.name='IFZI-R32' AND r.protected=0 AND r.review_status<>'approved';

UPDATE product_image_reviews r JOIN products p ON p.id=r.product_id
SET r.review_status='recovered',r.candidate_path='/product-image-candidates/idv-v100wdn1-d.webp',
    r.candidate_width=600,r.candidate_height=600,r.source_catalog='Catalogo VRF V8 IDEMA 2026',r.source_page=6,
    r.notes='Render estratto dal catalogo e verificato visivamente rispetto all’immagine attuale.',r.reviewed_at=CURRENT_TIMESTAMP
WHERE p.name='IDV-V100WDN1(D)' AND r.protected=0 AND r.review_status<>'approved';
