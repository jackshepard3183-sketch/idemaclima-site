INSERT INTO assistance_resources(section,label,document_id,external_url,sort_order,published)
SELECT 'warranty','Garanzia 2 anni',NULL,'https://www.idemaclima.it/wp-content/uploads/GARANZIA-2-ANNI.pdf',10,1
WHERE NOT EXISTS (SELECT 1 FROM assistance_resources WHERE section='warranty' AND label='Garanzia 2 anni' AND archived_at IS NULL);

INSERT INTO assistance_resources(section,label,document_id,external_url,sort_order,published)
SELECT 'warranty','Garanzia 5 anni',NULL,'https://www.idemaclima.it/wp-content/uploads/GARANZIA-5-ANNI.pdf',20,1
WHERE NOT EXISTS (SELECT 1 FROM assistance_resources WHERE section='warranty' AND label='Garanzia 5 anni' AND archived_at IS NULL);

INSERT INTO assistance_resources(section,label,document_id,external_url,sort_order,published)
SELECT 'warranty','Garanzia 10 anni (5+5)',NULL,'https://www.idemaclima.it/wp-content/uploads/GARANZIA-10-5_5-ANNI.pdf',30,1
WHERE NOT EXISTS (SELECT 1 FROM assistance_resources WHERE section='warranty' AND label='Garanzia 10 anni (5+5)' AND archived_at IS NULL);

INSERT INTO assistance_resources(section,label,document_id,external_url,sort_order,published)
SELECT 'warranty','Garanzia 5 anni (2+3)',NULL,'https://www.idemaclima.it/wp-content/uploads/GARANZIA-5-2_3-ANNI.pdf',40,1
WHERE NOT EXISTS (SELECT 1 FROM assistance_resources WHERE section='warranty' AND label='Garanzia 5 anni (2+3)' AND archived_at IS NULL);

INSERT INTO assistance_resources(section,label,document_id,external_url,sort_order,published)
SELECT 'error_code','MONO SPLIT — Linea Residenziale',NULL,'https://www.idemaclima.it/wp-content/uploads/IDEMA_Guida-codici-guasto-MONO-SPLIT_Linea-Residenziale.pdf',10,1
WHERE NOT EXISTS (SELECT 1 FROM assistance_resources WHERE section='error_code' AND label='MONO SPLIT — Linea Residenziale' AND archived_at IS NULL);

INSERT INTO assistance_resources(section,label,document_id,external_url,sort_order,published)
SELECT 'error_code','MONO SPLIT — Linea Commerciale',NULL,'https://www.idemaclima.it/wp-content/uploads/IDEMA_Guida-codici-guasto-MONO-SPLIT_Linea-Commerciale.pdf',20,1
WHERE NOT EXISTS (SELECT 1 FROM assistance_resources WHERE section='error_code' AND label='MONO SPLIT — Linea Commerciale' AND archived_at IS NULL);

INSERT INTO assistance_resources(section,label,document_id,external_url,sort_order,published)
SELECT 'error_code','MULTI SPLIT — Unità esterne',NULL,'https://www.idemaclima.it/wp-content/uploads/IDEMA_Guida-codici-guasto-MULTI-SPLIT_Unita-esterne.pdf',30,1
WHERE NOT EXISTS (SELECT 1 FROM assistance_resources WHERE section='error_code' AND label='MULTI SPLIT — Unità esterne' AND archived_at IS NULL);
