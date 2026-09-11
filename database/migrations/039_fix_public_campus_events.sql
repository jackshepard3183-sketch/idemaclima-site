DELETE FROM events WHERE slug = 'test-collaudo-flusso-dati-campus';

INSERT INTO events
(title,slug,audience,category,location,address,starts_at,ends_at,short_description,speaker,description,program,cover_image,max_seats,waitlist_enabled,registration_open,registration_deadline,published,cancelled,sort_order)
VALUES
('R290: prodotti, sicurezza e limiti operativi. Confronto con R32 e R410A',
 'r290-prodotti-sicurezza-limiti-operativi-r32-r410a-08-10-2026',
 'public','Pompe di calore','IDEMA CLIMA S.r.l.','S.S. dei Giovi, 31 - 22070 Vertemate con Minoprio (CO)',
 '2026-10-08 14:00:00','2026-10-08 17:00:00',
 'Incontro tecnico-commerciale dedicato al refrigerante R290, con un approccio pratico per installatori, progettisti e agenzie commerciali.',
 'IDEMA CLIMA S.r.l.',
 'Partiremo dalle caratteristiche dell’R290 per analizzare prestazioni, limiti operativi e requisiti di installazione, con particolare attenzione alla zona di installazione, alla carica di refrigerante e alla sicurezza del propano. Completeremo il quadro con un confronto diretto con R32 e R410A, analizzando prestazioni, sicurezza, requisiti di installazione, limiti di potenza e principali scadenze normative.\n\nL’attività è rivolta a installatori, progettisti e agenzie commerciali e favorisce un confronto concreto tra i professionisti coinvolti nella progettazione, commercializzazione, installazione e gestione dei sistemi a pompa di calore.\n\nQuota di iscrizione: € 50, rimborsati sul successivo ordine effettuato in caso di partecipazione.',
 'Parte teorica\n\n1. R290: caratteristiche e peculiarità del propano\n2. Confronto R290, R32 e R410A: sicurezza, GWP, prestazioni e applicazioni\n3. Limiti operativi: temperature, pressioni e prestazioni\n4. Zona di installazione: carica, ambiente, ventilazione e fonti di innesco\n5. Requisiti e qualificazione dell’installatore\n6. Limiti di potenza e normativa F-Gas\n7. Limiti e scadenze del Regolamento UE 2024/573\n8. Esempi pratici, domande e confronto finale',
 'https://www.idemaclima.it/wp-content/uploads/idema-campus.jpg',NULL,1,1,'2026-10-02 23:59:59',1,0,0)
ON DUPLICATE KEY UPDATE
 title=VALUES(title),audience=VALUES(audience),category=VALUES(category),location=VALUES(location),address=VALUES(address),starts_at=VALUES(starts_at),ends_at=VALUES(ends_at),short_description=VALUES(short_description),speaker=VALUES(speaker),description=VALUES(description),program=VALUES(program),cover_image=VALUES(cover_image),waitlist_enabled=VALUES(waitlist_enabled),registration_open=VALUES(registration_open),registration_deadline=VALUES(registration_deadline),published=VALUES(published),cancelled=VALUES(cancelled);
