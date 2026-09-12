-- Immagini originali dei prodotti, copertine Distribuzione aria e raccolta CE.
UPDATE products SET image_path=CASE slug
 WHEN 'terminali-idronici--ihw' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/UI_FANCOIL_IHW.png'
 WHEN 'terminali-idronici--ifs' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/UI_FANCOIL_IFS.png'
 WHEN 'terminali-idronici--imkg-v-b' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/FC_PARETE-B.png'
 WHEN 'terminali-idronici--imkg-v-c' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/FC_PARETE-C.png'
 WHEN 'terminali-idronici--imkd-v' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/FC_4VIE-600X600.png'
 WHEN 'terminali-idronici--imka-v' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/FC_4VIE-840X840.png'
 WHEN 'terminali-idronici--imkh2-v' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/FC_SOFFITTO-PAVIMENTO.png'
 WHEN 'terminali-idronici--imkt2-v' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/FC_CANALIZZABILE.png'
 WHEN 'terminali-idronici--imkt3-v' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/FC_CANALIZZABILE.png'
 WHEN 'terminali-idronici--imk-cbs' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/FC_CANALIZZABILE_MK-CBS.png'
 WHEN 'purificatori-aria--ftxm-740xit' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/FTXM-740XIT.png'
 WHEN 'barriere-lama-aria--ac-sa1' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/BARRIERE-ARIA_AC-SA1.png'
 WHEN 'barriere-lama-aria--ac-re' THEN 'https://www.idemaclima.it/wp-content/uploads/immagini/BARRIERE-ARIA_AC-RE.png'
 WHEN 'distribuzione-aria--sistemi-easy-kit' THEN 'https://www.idemaclima.it/wp-content/uploads/yootheme/cache/28/SISTEMI-EASY-KIT-ZONE-2022-284c377e.jpg'
 WHEN 'distribuzione-aria--sistema-radio' THEN 'https://www.idemaclima.it/wp-content/uploads/yootheme/cache/89/SISTEMA-RADIO-2022-89331d53.jpg'
 WHEN 'distribuzione-aria--componenti' THEN 'https://www.idemaclima.it/wp-content/uploads/logo-pdf.png'
 WHEN 'distribuzione-aria--modulo-plenum' THEN 'https://www.idemaclima.it/wp-content/uploads/logo-pdf.png'
 ELSE image_path END
WHERE slug IN ('terminali-idronici--ihw','terminali-idronici--ifs','terminali-idronici--imkg-v-b','terminali-idronici--imkg-v-c','terminali-idronici--imkd-v','terminali-idronici--imka-v','terminali-idronici--imkh2-v','terminali-idronici--imkt2-v','terminali-idronici--imkt3-v','terminali-idronici--imk-cbs','purificatori-aria--ftxm-740xit','barriere-lama-aria--ac-sa1','barriere-lama-aria--ac-re','distribuzione-aria--sistemi-easy-kit','distribuzione-aria--sistema-radio','distribuzione-aria--componenti','distribuzione-aria--modulo-plenum');

UPDATE editorial_pages SET title='Dichiarazioni Conformità CE',eyebrow='Schede tecniche',intro='Dichiarazioni di conformità CE ufficiali, organizzate per linea di prodotto.' WHERE slug='schede-tecniche/dichiarazioni-conformita-ce';

INSERT INTO document_types(name,slug,sort_order,active) VALUES ('Dichiarazioni di conformità CE','dichiarazioni-conformita-ce',5,1)
ON DUPLICATE KEY UPDATE active=1;

INSERT INTO documents(document_type_id,title,filename,file_path,published,sort_order)
SELECT dt.id,x.title,x.filename,x.file_path,1,x.sort_order FROM document_types dt JOIN (
 SELECT 'Linea Residenziale' title,'DICH-CONF-CE-LINEA-RESIDENZIALE.pdf' filename,'https://www.idemaclima.it/wp-content/uploads/schede/DICH-CONF-CE-LINEA-RESIDENZIALE.pdf' file_path,10 sort_order
 UNION ALL SELECT 'Linea Residenziale WTZ e MWTZ','DICH-CONF-CE-LINEA-RESIDENZIALE-WTZ-MWTZ.pdf','https://www.idemaclima.it/wp-content/uploads/schede/DICH-CONF-CE-LINEA-RESIDENZIALE-WTZ-MWTZ.pdf',20
 UNION ALL SELECT 'Linea Commerciale','DICH-CONF-CE-LINEA-COMMERCIALE.pdf','https://www.idemaclima.it/wp-content/uploads/schede/DICH-CONF-CE-LINEA-COMMERCIALE.pdf',30
 UNION ALL SELECT 'Linea VRF','DICH-CONF-CE-LINEA-VRF.pdf','https://www.idemaclima.it/wp-content/uploads/schede/DICH-CONF-CE-LINEA-VRF.pdf',40
 UNION ALL SELECT 'Linea Idronica – PdC monoblocco','DICH-CONF-CE-PDC-MONOBLOCCO-IGC-IHC-IHR.pdf','https://www.idemaclima.it/wp-content/uploads/schede/DICH-CONF-CE-PDC-MONOBLOCCO-IGC-IHC-IHR.pdf',50
 UNION ALL SELECT 'Linea Idronica – Scaldacqua','DICH-CONF-CE-SCALDACQUA-ACS-SOL.pdf','https://www.idemaclima.it/wp-content/uploads/schede/DICH-CONF-CE-SCALDACQUA-ACS-SOL.pdf',60
 UNION ALL SELECT 'Altri prodotti – Recuperatori IDHR','DICH-CONF-CE-RECUPERATORI-IDHR.pdf','https://www.idemaclima.it/wp-content/uploads/schede/DICH-CONF-CE-RECUPERATORI-IDHR.pdf',70
 UNION ALL SELECT 'Accessori – Comandi remoti','DICH-CONF-CE-COMANDI-REMOTI.pdf','https://www.idemaclima.it/wp-content/uploads/schede/DICH-CONF-CE-COMANDI-REMOTI.pdf',80
) x ON dt.slug='dichiarazioni-conformita-ce'
WHERE NOT EXISTS (SELECT 1 FROM documents d WHERE d.file_path=x.file_path);

INSERT INTO editorial_page_documents(page_id,document_id,group_label,label,sort_order,published)
SELECT p.id,d.id,
 CASE WHEN d.title LIKE 'Linea Residenziale%' THEN 'Linea Residenziale' WHEN d.title='Linea Commerciale' THEN 'Linea Commerciale' WHEN d.title='Linea VRF' THEN 'Linea VRF' WHEN d.title LIKE 'Linea Idronica%' THEN 'Linea Idronica' WHEN d.title LIKE 'Altri prodotti%' THEN 'Altri prodotti' ELSE 'Accessori' END,
 d.title,d.sort_order,1
FROM editorial_pages p JOIN documents d ON d.document_type_id=(SELECT id FROM document_types WHERE slug='dichiarazioni-conformita-ce' LIMIT 1)
WHERE p.slug='schede-tecniche/dichiarazioni-conformita-ce'
AND NOT EXISTS (SELECT 1 FROM editorial_page_documents epd WHERE epd.page_id=p.id AND epd.document_id=d.id);
