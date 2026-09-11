-- Pulizia dei dati di collaudo: restano amministratori, eventi, configurazioni e contenuti.
SET FOREIGN_KEY_CHECKS=0;
DELETE FROM warranty_generated_certificates;
DELETE FROM warranty_units;
DELETE FROM warranty_registration_details;
DELETE FROM warranty_registrations;
DELETE FROM event_registrations;
DELETE FROM incentive_requests;
DELETE FROM contact_submissions;
DELETE FROM cat_accounts;
DELETE FROM document_events;
SET FOREIGN_KEY_CHECKS=1;

INSERT INTO site_settings(setting_group,setting_key,setting_value) VALUES
('general','company_name','Idema Clima Srl'),
('general','registered_office','Corso di Porta Vittoria, 50 - 20122 Milano'),
('general','operational_office','S.S. dei Giovi, 31 - 22070 Vertemate con Minoprio (CO)'),
('general','phone','031 888 1637'),
('general','email','info@idemaclima.it'),
('general','pec','idemaclimasrl@legalmail.it'),
('general','company_data','Capitale sociale € 250.000 i.v.\nPartita IVA / Codice Fiscale / Registro Imprese Milano 03293510966\nREA Milano 1664622\nRegistro RAEE IT14030000008296\nRegistro PA IT19080P00005580'),
('site','copyright','Copyright © Idema Clima Srl. Tutti i diritti riservati.'),
('site','privacy_url','https://www.iubenda.com/privacy-policy/38092343'),
('site','cookie_url','https://www.iubenda.com/privacy-policy/38092343/cookie-policy'),
('site','price_list_url','https://www.rappresentanzeguanzirolisas.it/idemaclima/uploads/mirrored/bfc5b0bf6600b357-LISTINO_PREZZI_IDEMA_2026.pdf'),
('site','seo_title','IDEMA CLIMA® — Climatizzatori e pompe di calore ad alta efficienza'),
('site','seo_description','Climatizzatori, pompe di calore e soluzioni IDEMA CLIMA® per il comfort e l’efficienza energetica.')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=CURRENT_TIMESTAMP;

ALTER TABLE products ADD COLUMN source_catalog_url VARCHAR(1000) NULL AFTER image_path;
ALTER TABLE products ADD COLUMN source_catalog_page SMALLINT UNSIGNED NULL AFTER source_catalog_url;

UPDATE products SET source_catalog_url='https://www.rappresentanzeguanzirolisas.it/idemaclima/uploads/mirrored/bfc5b0bf6600b357-LISTINO_PREZZI_IDEMA_2026.pdf',
 source_catalog_page=CASE name WHEN 'ISPT-R32' THEN 4 WHEN 'ISAX-R32' THEN 5 WHEN 'WTMC-R32' THEN 6 WHEN 'WTMC-R32 COLOR' THEN 7 WHEN 'ISZZ-R32' THEN 8 WHEN 'WTZ-R32' THEN 9 END
WHERE name IN ('ISPT-R32','ISAX-R32','WTMC-R32','WTMC-R32 COLOR','ISZZ-R32','WTZ-R32');

UPDATE products SET description=CASE name
WHEN 'ISPT-R32' THEN 'Sistema Mono Split DC Inverter in pompa di calore ad altissima efficienza con sensore di presenza, Wi‑Fi di serie, alette motorizzate e Active Clean.'
WHEN 'ISAX-R32' THEN 'Sistema Mono Split DC Inverter ad alta efficienza con Wi‑Fi di serie, alette motorizzate e filtro super ionizzatore germicida.'
WHEN 'WTMC-R32' THEN 'Sistema Mono Split DC Inverter ad alta efficienza con Wi‑Fi di serie, filtro agli ioni negativi e alette motorizzate.'
WHEN 'WTMC-R32 COLOR' THEN 'Sistema Mono Split DC Inverter ad alta efficienza in finitura BLACK, con Wi‑Fi di serie, filtro agli ioni negativi e alette motorizzate.'
WHEN 'ISZZ-R32' THEN 'Sistema Mono Split DC Inverter in pompa di calore con predisposizione Wi‑Fi.'
WHEN 'WTZ-R32' THEN 'Sistema Mono Split DC Inverter in pompa di calore con predisposizione Wi‑Fi.' END
WHERE name IN ('ISPT-R32','ISAX-R32','WTMC-R32','WTMC-R32 COLOR','ISZZ-R32','WTZ-R32');

DELETE pf FROM product_features pf JOIN products p ON p.id=pf.product_id WHERE p.name IN ('ISPT-R32','ISAX-R32','WTMC-R32','WTMC-R32 COLOR','ISZZ-R32','WTZ-R32');
INSERT INTO product_features(product_id,label,sort_order)
SELECT id,'Dispositivo Wi‑Fi di serie (compatibile Alexa e Google Home)',0 FROM products WHERE name IN ('ISPT-R32','ISAX-R32','WTMC-R32','WTMC-R32 COLOR')
UNION ALL SELECT id,'Gas refrigerante R32 a basso impatto ambientale',1 FROM products WHERE name IN ('ISPT-R32','ISAX-R32','WTMC-R32','WTMC-R32 COLOR','ISZZ-R32','WTZ-R32')
UNION ALL SELECT id,'Alette orizzontali e verticali motorizzate',2 FROM products WHERE name IN ('ISPT-R32','ISAX-R32','WTMC-R32','WTMC-R32 COLOR')
UNION ALL SELECT id,'Predisposizione Wi‑Fi',0 FROM products WHERE name IN ('ISZZ-R32','WTZ-R32')
UNION ALL SELECT id,'Sensore di presenza incorporato nel pannello',3 FROM products WHERE name='ISPT-R32'
UNION ALL SELECT id,'Filtro super ionizzatore germicida incluso',3 FROM products WHERE name='ISAX-R32'
UNION ALL SELECT id,'Filtro agli ioni negativi',3 FROM products WHERE name IN ('WTMC-R32','WTMC-R32 COLOR');

-- FAQ generale: contenuti completi del sito ufficiale.
DELETE f FROM faq_items f JOIN editorial_pages p ON p.id=f.page_id WHERE p.slug='faq';
INSERT INTO faq_items(page_id,question,answer,sort_order,published)
SELECT id,'Chi può installare un climatizzatore?','L’installazione deve essere eseguita da un’impresa abilitata ai sensi del D.M. 37/2008 e in possesso dei requisiti F-GAS previsti per operare su apparecchiature contenenti gas fluorurati.',10,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Cosa copre la garanzia IDEMA?','La garanzia copre i difetti di conformità e di fabbricazione secondo termini e condizioni applicabili. Installazione, manutenzione e uso devono essere conformi ai manuali; materiali di consumo e danni da cause esterne non sono inclusi.',20,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Come si calcola la potenza necessaria?','Il corretto dimensionamento dipende da volume, esposizione, isolamento, superfici vetrate, destinazione d’uso e carichi interni. È consigliato il calcolo di un tecnico qualificato.',30,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Cos’è una pompa di calore?','È una macchina che trasferisce calore da un ambiente a un altro: raffresca in estate e, invertendo il ciclo, riscalda in inverno con elevata efficienza.',40,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Cosa significa BTU/h?','Il BTU/h è un’unità di misura della potenza termica. Per scegliere la taglia corretta non basta la sola superficie: serve valutare le caratteristiche reali dell’ambiente.',50,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Cos’è la classe energetica?','Indica l’efficienza stagionale dell’apparecchio. Le classi più elevate corrispondono in genere a consumi inferiori a parità di condizioni d’uso.',60,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Quali temperature è consigliabile impostare?','In raffrescamento è consigliabile evitare differenze eccessive rispetto all’esterno; in riscaldamento attenersi ai limiti di legge e alle condizioni di comfort, usando una regolazione stabile.',70,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Quando va ricaricato il gas refrigerante?','Il circuito è chiuso e non richiede ricariche periodiche. Se manca refrigerante deve essere individuata e riparata l’eventuale perdita da personale certificato F-GAS.',80,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Perché il climatizzatore non raffredda o non riscalda abbastanza?','Verificare modalità e temperatura impostate, filtri, porte e finestre, ostacoli al flusso d’aria e corretto dimensionamento. Se il problema persiste rivolgersi all’assistenza qualificata.',90,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Cos’è la funzione di preriscaldo?','In riscaldamento l’unità interna può ritardare la ventilazione per evitare l’immissione di aria fredda; è un comportamento normale.',100,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Perché l’unità interna perde acqua?','Le cause più comuni sono scarico condensa ostruito o installato male, filtri sporchi o isolamento non corretto. Spegnere l’apparecchio e farlo verificare da un tecnico.',110,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Come si puliscono i filtri?','Spegnere e disalimentare l’unità, rimuovere i filtri seguendo il manuale, aspirarli o lavarli con acqua tiepida e lasciarli asciugare completamente prima di rimontarli.',120,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'È possibile nascondere le unità?','Non coprire né ostacolare prese e mandata d’aria. Eventuali schermature devono garantire gli spazi tecnici del manuale e l’accessibilità per la manutenzione.',130,1 FROM editorial_pages WHERE slug='faq'
UNION ALL SELECT id,'Quale manutenzione è necessaria?','Pulire periodicamente i filtri e far controllare impianto, scarico condensa, scambiatori e collegamenti da personale qualificato secondo uso, manuale e normativa locale.',140,1 FROM editorial_pages WHERE slug='faq';

UPDATE editorial_pages SET title='FAQ',eyebrow='Domande e risposte',intro='Le risposte alle domande più frequenti su installazione, garanzia, uso, manutenzione ed efficienza energetica dei climatizzatori IDEMA.',meta_title='FAQ climatizzatori e pompe di calore | IDEMA CLIMA' WHERE slug='faq';

-- Accordion completo “Libretto d’impianto”.
DELETE f FROM faq_items f JOIN editorial_pages p ON p.id=f.page_id WHERE p.slug='detrazioni-e-incentivi';
INSERT INTO faq_items(page_id,question,answer,sort_order,published)
SELECT id,'Cos’è un impianto termico?','L’impianto termico è destinato alla climatizzazione invernale e/o estiva, con o senza acqua calda sanitaria, e comprende produzione, distribuzione, utilizzazione, regolazione e controllo. Non sono impianti termici i sistemi dedicati esclusivamente all’acqua calda sanitaria di singole unità residenziali.',10,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi'
UNION ALL SELECT id,'Quando va compilato il libretto?','Alla realizzazione di un nuovo impianto oppure, per un impianto esistente, al primo intervento utile di controllo o manutenzione effettuato da personale abilitato.',20,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi'
UNION ALL SELECT id,'Chi deve compilarlo?','La responsabilità è del proprietario, amministratore, terzo responsabile oppure occupante dell’unità. Installatore o manutentore compilano le parti tecniche; il responsabile conserva il nuovo e il vecchio libretto. Occorre verificare eventuali modelli regionali.',30,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi'
UNION ALL SELECT id,'In cosa consiste la compilazione?','Il libretto è unico ma composto da schede selezionate secondo la tipologia d’impianto. Di norma ne va compilato uno per edificio/impianto; installatore o manutentore inviano i dati al catasto regionale e riportano il codice catastale.',40,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi'
UNION ALL SELECT id,'Cosa sono i controlli periodici di efficienza energetica?','Attestano l’efficienza degli impianti fissi. Sono previsti per generatori a fiamma da 10 kW e per climatizzazione estiva/invernale da 12 kW, con periodicità e modalità del D.P.R. 74/2013 e delle disposizioni regionali. Per apparecchi singoli sotto 12 kW non si compilano i rapporti, salvo regole regionali diverse.',50,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi'
UNION ALL SELECT id,'Il caso della Regione Lombardia','In Lombardia sotto 12 kW il libretto non è obbligatorio, ma è consigliata la registrazione CURIT. Per verificare la soglia, la Regione somma anche le potenze dei singoli sistemi split/pompe di calore presenti nella stessa unità immobiliare.',60,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi';
