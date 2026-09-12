-- Completa le famiglie storiche delle Schede tecniche mantenendo la gerarchia
-- Linea -> Famiglia -> Prodotto e rende autonoma la navigazione dello staging.

INSERT INTO product_categories(parent_id,name,slug,sort_order,content_status,published)
SELECT NULL,'Distribuzione aria','distribuzione-aria',60,'published',1
WHERE NOT EXISTS (SELECT 1 FROM product_categories WHERE slug='distribuzione-aria');

INSERT INTO product_categories(parent_id,name,slug,sort_order,content_status,published)
SELECT p.id,x.name,x.slug,x.sort_order,'published',1
FROM product_categories p
JOIN (
 SELECT 'linea-idronica' parent_slug,'Terminali idronici' name,'linea-idronica--linea-idronica-terminali-idronici' slug,5 sort_order
 UNION ALL SELECT 'altri-prodotti','Purificatori d’aria','altri-prodotti--altri-prodotti-purificatori-aria',20
 UNION ALL SELECT 'altri-prodotti','Barriere a lama d’aria','altri-prodotti--altri-prodotti-barriere-lama-aria',30
 UNION ALL SELECT 'distribuzione-aria','Sistemi e componenti','distribuzione-aria--sistemi-e-componenti',10
) x ON x.parent_slug=p.slug
WHERE NOT EXISTS (SELECT 1 FROM product_categories c WHERE c.slug=x.slug);

INSERT INTO products(category_id,name,slug,description,product_role,status,content_status,sort_order,published)
SELECT c.id,x.name,x.slug,x.description,'other',x.status,'published',x.sort_order,1
FROM product_categories c
JOIN (
 SELECT 'linea-idronica--linea-idronica-terminali-idronici' family_slug,'IHW' name,'terminali-idronici--ihw' slug,'Terminale idronico serie Vitro con pannello estetico in cristallo temperato bianco opaco per installazione a parete alta e motore DC Inverter.' description,'active' status,10 sort_order
 UNION ALL SELECT 'linea-idronica--linea-idronica-terminali-idronici','IFS','terminali-idronici--ifs','Terminale idronico serie Vitro per installazione a pavimento, motore DC Inverter e attacchi idraulici a destra e a sinistra.','active',20
 UNION ALL SELECT 'linea-idronica--linea-idronica-terminali-idronici','IMKG-V (B)','terminali-idronici--imkg-v-b','Fancoil a parete a 2 tubi con motore DC Inverter e valvola a 3 vie di serie.','active',30
 UNION ALL SELECT 'linea-idronica--linea-idronica-terminali-idronici','IMKG-V (C)','terminali-idronici--imkg-v-c','Fancoil a parete a 2 tubi con motore DC Inverter e valvola a 3 vie di serie.','active',40
 UNION ALL SELECT 'linea-idronica--linea-idronica-terminali-idronici','IMKD-V','terminali-idronici--imkd-v','Fancoil a cassetta 4 vie compatta 600×600 con motore DC Inverter e pompa di scarico condensa.','active',50
 UNION ALL SELECT 'linea-idronica--linea-idronica-terminali-idronici','IMKA-V','terminali-idronici--imka-v','Fancoil a cassetta 4 vie 840×840 con motore DC Inverter e pompa di scarico condensa.','active',60
 UNION ALL SELECT 'linea-idronica--linea-idronica-terminali-idronici','IMKH2-V','terminali-idronici--imkh2-v','Fancoil soffitto/pavimento a 2 tubi con motore DC Inverter.','active',70
 UNION ALL SELECT 'linea-idronica--linea-idronica-terminali-idronici','IMKT2-V','terminali-idronici--imkt2-v','Fancoil canalizzabile con batteria a 2 ranghi per impianti a 2 tubi e motore DC Inverter.','active',80
 UNION ALL SELECT 'linea-idronica--linea-idronica-terminali-idronici','IMKT3-V','terminali-idronici--imkt3-v','Fancoil canalizzabile con batteria a 3 ranghi per impianti a 2 tubi e motore DC Inverter.','active',90
 UNION ALL SELECT 'linea-idronica--linea-idronica-terminali-idronici','IMK-CBS','terminali-idronici--imk-cbs','Fancoil canalizzabile con batteria a 3 ranghi, motore DC Inverter e prevalenza statica fino a 50 Pa.','active',100
 UNION ALL SELECT 'altri-prodotti--altri-prodotti-purificatori-aria','FTXM-740XIT','purificatori-aria--ftxm-740xit','Purificatore d’aria con filtrazione a 5 stadi, filtro HEPA, carboni attivi, ionizzazione, CADR 740 m³/h ed efficienza del 99,97%.','active',10
 UNION ALL SELECT 'altri-prodotti--altri-prodotti-barriere-lama-aria','AC-SA1','barriere-lama-aria--ac-sa1','Barriera a lama d’aria solo ventilazione, con 3 velocità, comando a bordo e telecomando a infrarossi.','unavailable',10
 UNION ALL SELECT 'altri-prodotti--altri-prodotti-barriere-lama-aria','AC-RE','barriere-lama-aria--ac-re','Barriera a lama d’aria con resistenza elettrica, 3 velocità, comando a bordo e telecomando a infrarossi.','unavailable',20
 UNION ALL SELECT 'distribuzione-aria--sistemi-e-componenti','Sistemi Easy Kit','distribuzione-aria--sistemi-easy-kit','Kit completi per la zonificazione e la distribuzione dell’aria.','active',10
 UNION ALL SELECT 'distribuzione-aria--sistemi-e-componenti','Sistema Radio','distribuzione-aria--sistema-radio','Sistema radio per la zonificazione degli impianti.','active',20
 UNION ALL SELECT 'distribuzione-aria--sistemi-e-componenti','Componenti distribuzione aria','distribuzione-aria--componenti','Componenti dedicati alla distribuzione dell’aria.','active',30
 UNION ALL SELECT 'distribuzione-aria--sistemi-e-componenti','Modulo richiesta plenum','distribuzione-aria--modulo-plenum','Modulo per la richiesta di plenum su misura.','active',40
) x ON x.family_slug=c.slug
WHERE NOT EXISTS (SELECT 1 FROM products p WHERE p.slug=x.slug);

INSERT INTO document_types(name,slug,sort_order,active) VALUES
('Manuali','manuali',50,1),('Documentazione commerciale','documentazione-commerciale',60,1),('Modulistica','modulistica',70,1)
ON DUPLICATE KEY UPDATE active=1;

INSERT INTO documents(document_type_id,title,filename,file_path,published,sort_order)
SELECT dt.id,x.title,x.filename,x.file_path,1,x.sort_order
FROM document_types dt
JOIN (
 SELECT 'manuali' type_slug,'Manuale uso FTXM-740XIT' title,'UM_FTXM-740XIT.pdf' filename,'https://www.idemaclima.it/wp-content/uploads/schede/UM_FTXM-740XIT.pdf' file_path,10 sort_order
 UNION ALL SELECT 'manuali','Manuale installazione AC-SA1','IM_AC-SA1.pdf','https://www.idemaclima.it/wp-content/uploads/schede/IM_AC-SA1.pdf',20
 UNION ALL SELECT 'manuali','Manuale installazione AC-RE','IM_AC-RE.pdf','https://www.idemaclima.it/wp-content/uploads/schede/IM_AC-RE.pdf',30
 UNION ALL SELECT 'documentazione-commerciale','Sistemi Easy Kit Zone','SISTEMI-EASY-KIT-ZONE-2022.pdf','https://www.idemaclima.it/wp-content/uploads/SISTEMI-EASY-KIT-ZONE-2022.pdf',40
 UNION ALL SELECT 'documentazione-commerciale','Sistema Radio','SISTEMA-RADIO-2022.pdf','https://www.idemaclima.it/wp-content/uploads/SISTEMA-RADIO-2022.pdf',50
 UNION ALL SELECT 'documentazione-commerciale','Componenti distribuzione aria','COMPONENTI-DISTRIBUZIONE-ARIA-2022.pdf','https://www.idemaclima.it/wp-content/uploads/COMPONENTI-DISTRIBUZIONE-ARIA-2022.pdf',60
 UNION ALL SELECT 'modulistica','Modulo richiesta plenum','MODULO-RICHIESTA-PLENUM-2022.pdf','https://www.idemaclima.it/wp-content/uploads/MODULO-RICHIESTA-PLENUM-2022.pdf',70
) x ON x.type_slug=dt.slug
WHERE NOT EXISTS (SELECT 1 FROM documents d WHERE d.file_path=x.file_path);

INSERT INTO document_links(document_id,product_id)
SELECT d.id,p.id FROM (
 SELECT 'https://www.idemaclima.it/wp-content/uploads/schede/UM_FTXM-740XIT.pdf' file_path,'purificatori-aria--ftxm-740xit' product_slug
 UNION ALL SELECT 'https://www.idemaclima.it/wp-content/uploads/schede/IM_AC-SA1.pdf','barriere-lama-aria--ac-sa1'
 UNION ALL SELECT 'https://www.idemaclima.it/wp-content/uploads/schede/IM_AC-RE.pdf','barriere-lama-aria--ac-re'
 UNION ALL SELECT 'https://www.idemaclima.it/wp-content/uploads/SISTEMI-EASY-KIT-ZONE-2022.pdf','distribuzione-aria--sistemi-easy-kit'
 UNION ALL SELECT 'https://www.idemaclima.it/wp-content/uploads/SISTEMA-RADIO-2022.pdf','distribuzione-aria--sistema-radio'
 UNION ALL SELECT 'https://www.idemaclima.it/wp-content/uploads/COMPONENTI-DISTRIBUZIONE-ARIA-2022.pdf','distribuzione-aria--componenti'
 UNION ALL SELECT 'https://www.idemaclima.it/wp-content/uploads/MODULO-RICHIESTA-PLENUM-2022.pdf','distribuzione-aria--modulo-plenum'
) x JOIN documents d ON d.file_path=x.file_path JOIN products p ON p.slug=x.product_slug
WHERE NOT EXISTS (SELECT 1 FROM document_links dl WHERE dl.document_id=d.id AND dl.product_id=p.id);

