ALTER TABLE events
  ADD COLUMN fee_amount DECIMAL(10,2) NULL AFTER registration_deadline,
  ADD COLUMN fee_note VARCHAR(255) NULL AFTER fee_amount;

UPDATE events
SET
  short_description = 'Incontro tecnico-commerciale dedicato al refrigerante R290, con un approccio pratico rivolto a installatori, progettisti e agenzie commerciali.',
  description = 'L’incontro parte dalle caratteristiche del refrigerante R290 per analizzarne prestazioni, limiti operativi e requisiti di installazione, con particolare attenzione alla zona di installazione, alla carica di refrigerante e alla sicurezza del propano. Il quadro viene completato da un confronto diretto con R32 e R410A, considerando prestazioni, sicurezza, requisiti di installazione, limiti di potenza e principali scadenze normative.\n\nL’attività è rivolta a installatori, progettisti e agenzie commerciali e offre un’occasione di confronto concreto tra i professionisti coinvolti nella progettazione, commercializzazione, installazione e gestione dei sistemi a pompa di calore.',
  fee_amount = 50.00,
  fee_note = 'Quota rimborsabile sul successivo ordine effettuato in caso di partecipazione.'
WHERE slug = 'r290-prodotti-sicurezza-limiti-operativi-r32-r410a-08-10-2026';
