-- Riallineamento backend dopo le migrazioni incrementali già registrate.
-- Non modifica pagine o contenuti grafici del frontend.

UPDATE product_categories SET sort_order=CASE name
 WHEN 'Linea Residenziale R32' THEN 10
 WHEN 'Linea Commerciale R32' THEN 20
 WHEN 'Linea VRF' THEN 30
 WHEN 'Linea Idronica' THEN 40
 WHEN 'Altri Prodotti' THEN 50
 WHEN 'Distribuzione aria' THEN 60
 ELSE sort_order END
WHERE parent_id IS NULL;

UPDATE product_categories c
JOIN product_categories p ON p.id=c.parent_id
SET c.sort_order=CASE
 WHEN p.name='Linea Residenziale R32' AND c.name='Mono Split' THEN 10
 WHEN p.name='Linea Residenziale R32' AND c.name='Multi Split' THEN 20
 WHEN p.name='Linea Residenziale R32' AND c.name='Multi Pro' THEN 30
 WHEN p.name='Linea Residenziale R32' AND c.name='Accessori' THEN 40
 WHEN p.name='Linea Commerciale R32' AND c.name='Unità interne' THEN 10
 WHEN p.name='Linea Commerciale R32' AND c.name='Unità esterne' THEN 20
 WHEN p.name='Linea Commerciale R32' AND c.name='Accessori' THEN 30
 WHEN p.name='Linea VRF' AND c.name='Atomix R32' THEN 10
 WHEN p.name='Linea VRF' AND c.name='Mini VRF Monoventola' THEN 20
 WHEN p.name='Linea VRF' AND c.name='Unità interne' THEN 30
 WHEN p.name='Linea VRF' AND c.name='VRF Side Discharge' THEN 40
 WHEN p.name='Linea VRF' AND c.name='VRF Top Discharge' THEN 50
 WHEN p.name='Linea VRF' AND c.name='Accessori' THEN 60
 WHEN p.name='Linea Idronica' AND c.name='PdC All-in-One' THEN 10
 WHEN p.name='Linea Idronica' AND c.name='PdC Monoblocco' THEN 20
 WHEN p.name='Linea Idronica' AND c.name='Scaldacqua' THEN 30
 WHEN p.name='Linea Idronica' AND c.name='Terminali idronici' THEN 40
 WHEN p.name='Altri Prodotti' AND c.name='Purificatori d’aria' THEN 10
 WHEN p.name='Altri Prodotti' AND c.name='Barriere a lama d’aria' THEN 20
 WHEN p.name='Altri Prodotti' AND c.name='Recuperatori di calore' THEN 30
 WHEN p.name='Distribuzione aria' AND c.name='Sistemi e componenti' THEN 10
 ELSE c.sort_order END;

UPDATE products SET
 source_catalog_url=CASE name
  WHEN 'ISPT-R32' THEN 'https://www.rappresentanzeguanzirolisas.it/idemaclima/uploads/catalogs/2026/09/ispt-r32-2026-eb4fc603cbc2.pdf'
  WHEN 'ISAX-R32' THEN 'https://www.rappresentanzeguanzirolisas.it/idemaclima/uploads/catalogs/2026/09/isax-r32-2026-b9bc81f37c87.pdf'
  WHEN 'ISZZ-R32' THEN 'https://www.rappresentanzeguanzirolisas.it/idemaclima/uploads/catalogs/2026/09/iszz-r32-2026-6e5f95e256f4.pdf'
  ELSE 'https://www.rappresentanzeguanzirolisas.it/idemaclima/uploads/catalogs/2026/09/c-wtmc-wtz-mw-r32-2026-9a21c0dc4190.pdf' END,
 source_catalog_page=CASE name
  WHEN 'ISPT-R32' THEN 4 WHEN 'ISAX-R32' THEN 5 WHEN 'WTMC-R32' THEN 6
  WHEN 'WTMC-R32 COLOR' THEN 7 WHEN 'ISZZ-R32' THEN 8 WHEN 'WTZ-R32' THEN 9 END
WHERE name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR');

DELETE pf FROM product_features pf JOIN products p ON p.id=pf.product_id
WHERE p.name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR');

INSERT INTO product_features(product_id,label,sort_order)
SELECT id,'Dispositivo Wi‑Fi di serie (compatibile Alexa e Google Home)',10 FROM products WHERE name IN ('ISPT-R32','ISAX-R32','WTMC-R32','WTMC-R32 COLOR')
UNION ALL SELECT id,'Predisposizione Wi‑Fi',10 FROM products WHERE name IN ('ISZZ-R32','WTZ-R32')
UNION ALL SELECT id,'Gas refrigerante R32 a basso impatto ambientale',20 FROM products WHERE name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR')
UNION ALL SELECT id,'Alette orizzontali e verticali motorizzate',30 FROM products WHERE name IN ('ISPT-R32','ISAX-R32','WTMC-R32','WTMC-R32 COLOR')
UNION ALL SELECT id,'Sensore di presenza incorporato nel pannello',40 FROM products WHERE name='ISPT-R32'
UNION ALL SELECT id,'Filtro super ionizzatore germicida incluso',40 FROM products WHERE name='ISAX-R32'
UNION ALL SELECT id,'Filtro agli ioni negativi',40 FROM products WHERE name IN ('WTMC-R32','WTMC-R32 COLOR');

DELETE ps FROM product_specifications ps JOIN products p ON p.id=ps.product_id
WHERE p.name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR');

INSERT INTO product_specifications(product_id,specification_key,specification_value,sort_order)
SELECT id,'Tipologia','Mono Split DC Inverter in pompa di calore',10 FROM products WHERE name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR')
UNION ALL SELECT id,'Gas refrigerante','R32',20 FROM products WHERE name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR')
UNION ALL SELECT id,'Connettività Wi‑Fi','Di serie',30 FROM products WHERE name IN ('ISPT-R32','ISAX-R32','WTMC-R32','WTMC-R32 COLOR')
UNION ALL SELECT id,'Connettività Wi‑Fi','Predisposizione',30 FROM products WHERE name IN ('ISZZ-R32','WTZ-R32');

DELETE pa FROM product_accessories pa JOIN products p ON p.id=pa.product_id
WHERE p.name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR');

INSERT INTO product_accessories(product_id,code,name,description,sort_order,published)
SELECT id,'EU-OSK109','Modulo Wi‑Fi','Modulo per configurazione e controllo tramite app; verificare la dotazione della specifica serie.',10,1
FROM products WHERE name IN ('ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR');
