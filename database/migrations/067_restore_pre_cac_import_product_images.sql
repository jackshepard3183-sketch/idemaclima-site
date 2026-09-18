-- Ripristino richiesto: stato immediatamente precedente all'importazione CAC-V6I 2024
-- e alla sostituzione delle immagini dei Mono Split.
UPDATE products
SET image_path = CASE id
  WHEN 1  THEN '/uploads/mirrored/33ae026b8f1f8ec3-UI_ISPT.png'
  WHEN 2  THEN '/uploads/mirrored/cb4b9d570f1884c5-UI_ISA-ISAT-ISAX.png'
  WHEN 3  THEN '/uploads/mirrored/d27f78a36db7922b-UI_ISZZ.png'
  WHEN 4  THEN '/uploads/mirrored/91116c57227f5a35-UI_WTZ.png'
  WHEN 5  THEN '/uploads/mirrored/a7801f7ade946295-UI_WTMC.png'
  WHEN 6  THEN '/uploads/mirrored/3dd3cc99a47f6e8a-UI_WTMC-BLK.png'
  WHEN 8  THEN '/uploads/mirrored/cb4b9d570f1884c5-UI_ISA-ISAT-ISAX.png'
  WHEN 9  THEN '/uploads/mirrored/cb4b9d570f1884c5-UI_ISA-ISAT-ISAX.png'
  WHEN 16 THEN '/uploads/mirrored/33ae026b8f1f8ec3-UI_ISPT.png'
  WHEN 21 THEN '/uploads/mirrored/cb4b9d570f1884c5-UI_ISA-ISAT-ISAX.png'
  WHEN 22 THEN '/uploads/mirrored/d27f78a36db7922b-UI_ISZZ.png'
  WHEN 35 THEN '/uploads/mirrored/cb4b9d570f1884c5-UI_ISA-ISAT-ISAX.png'
  WHEN 36 THEN '/uploads/mirrored/cb4b9d570f1884c5-UI_ISA-ISAT-ISAX.png'
  WHEN 43 THEN '/uploads/mirrored/91116c57227f5a35-UI_WTZ.png'
  WHEN 44 THEN '/uploads/mirrored/a7801f7ade946295-UI_WTMC.png'
  WHEN 45 THEN '/uploads/mirrored/3dd3cc99a47f6e8a-UI_WTMC-BLK.png'
  ELSE image_path
END
WHERE id IN (1,2,3,4,5,6,8,9,16,21,22,35,36,43,44,45);

UPDATE product_image_reviews r
JOIN (
  SELECT entity_id
  FROM audit_log
  WHERE action = 'product_image.bulk_import'
  ORDER BY id DESC
  LIMIT 9
) imported ON imported.entity_id = r.product_id
SET r.candidate_path = NULL,
    r.candidate_width = NULL,
    r.candidate_height = NULL,
    r.review_status = 'to_review',
    r.source_catalog = NULL,
    r.source_page = NULL,
    r.notes = NULL,
    r.reviewed_at = CURRENT_TIMESTAMP;
