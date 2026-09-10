-- Eventi Campus recuperati dal progetto Lovable IDEMA.
-- Il sito originale IDEMA e' stato usato per confermare gli orari locali e gli indirizzi.
-- La migrazione e' idempotente grazie allo slug univoco e non importa record di test o duplicati.

INSERT INTO events
  (title, slug, audience, category, location, address, starts_at, ends_at,
   short_description, speaker, description, program, cover_image, max_seats,
   waitlist_enabled, registration_open, registration_deadline, published, cancelled, sort_order)
VALUES
  (
    'Sistemi in PdC idronici e residenziali con produzione di ACS (pre-vendita)',
    'pdc-idronici-residenziali-acs-pre-vendita-14-11-2025',
    'public', 'Residenziale', 'Sede IDEMA CLIMA - Vertemate con Minoprio (CO)',
    'S.S. dei Giovi, 31 - 22070 Vertemate con Minoprio (CO)',
    '2025-11-14 10:00:00', '2025-11-14 12:30:00',
    'Valutazione degli impianti esistenti tipo e proposta dei sistemi IDEMA in pompa di calore.',
    NULL,
    'Come proporre i sistemi in pompa di calore idronici e i sistemi residenziali a recupero di calore ad espansione diretta con produzione di ACS. Valutazione degli impianti esistenti tipo in fase di pre-vendita. Corso svolto presso la sala Campus di IDEMA CLIMA.',
    NULL, NULL, NULL, 1, 0, NULL, 1, 0, 20
  ),
  (
    'Sistemi idronici, residenziali con ACS e VRF - Evento agenzie commerciali',
    'evento-agenzie-commerciali-vrf-03-12-2025',
    'public', 'Residenziale', 'Sede IDEMA CLIMA - Vertemate con Minoprio (CO)',
    'S.S. dei Giovi, 31 - 22070 Vertemate con Minoprio (CO)',
    '2025-12-03 09:00:00', '2025-12-03 13:00:00',
    'Evento dedicato alle agenzie commerciali IDEMA CLIMA sulla valutazione degli impianti in fase di pre-vendita.',
    NULL,
    'Evento dedicato alle agenzie commerciali di IDEMA CLIMA: sistemi in pompa di calore idronici, sistemi residenziali a recupero di calore ad espansione diretta con produzione di ACS e sistemi VRF. Valutazione degli impianti esistenti tipo in fase di pre-vendita. Corso svolto presso la sala Campus di IDEMA CLIMA.',
    NULL, NULL, NULL, 1, 0, NULL, 1, 0, 30
  ),
  (
    'Sistemi in PdC idronici e ad espansione diretta residenziali - Prato',
    'sistemi-pdc-idronici-prato-16-12-2025',
    'public', 'Residenziale', 'Prato',
    'Via Galcianese 67/1 - Prato',
    '2025-12-16 14:00:00', '2025-12-16 17:00:00',
    'Dimensionamento degli accumuli sanitari, valutazione degli impianti esistenti, incentivi fiscali e bonus.',
    NULL,
    'Come proporre i sistemi in pompa di calore idronici e i sistemi ad espansione diretta residenziali. Il corso affronta il dimensionamento degli accumuli sanitari abbinati alla pompa di calore, l''implementazione impiantistica nelle riqualificazioni e nelle nuove realizzazioni, la valutazione degli impianti esistenti e gli accorgimenti utili per il funzionamento dei nuovi generatori di calore. Sono inoltre trattati gli incentivi fiscali e i bonus in essere.',
    NULL, NULL, NULL, 1, 0, NULL, 1, 0, 40
  ),
  (
    'Sistemi in PdC idronici e ad espansione diretta residenziali - Sede IDEMA',
    'sistemi-pdc-idronici-vertemate-27-01-2026',
    'public', 'Residenziale', 'Sede IDEMA CLIMA - Vertemate con Minoprio (CO)',
    'S.S. dei Giovi, 31 - 22070 Vertemate con Minoprio (CO)',
    '2026-01-27 14:30:00', '2026-01-28 13:00:00',
    'Due giornate di formazione su dimensionamento, valutazione degli impianti e incentivi fiscali.',
    NULL,
    'Come proporre i sistemi in pompa di calore idronici e i sistemi ad espansione diretta residenziali. Il percorso approfondisce il dimensionamento degli accumuli sanitari, l''implementazione impiantistica nelle riqualificazioni e nelle nuove realizzazioni, la valutazione degli impianti esistenti e gli incentivi fiscali disponibili. Corso svolto presso la sala Campus di IDEMA CLIMA.',
    NULL, NULL, NULL, 1, 0, NULL, 1, 0, 50
  ),
  (
    'Sistemi in PdC idronici e ad espansione diretta residenziali - Sede IDEMA',
    'sistemi-pdc-idronici-vertemate-30-04-2026',
    'public', 'Residenziale', 'Sede IDEMA CLIMA - Vertemate con Minoprio (CO)',
    'S.S. dei Giovi, 31 - 22070 Vertemate con Minoprio (CO)',
    '2026-04-30 14:00:00', '2026-04-30 17:00:00',
    'Dimensionamento degli accumuli, valutazione degli impianti esistenti, incentivi fiscali e bonus.',
    NULL,
    'Come proporre i sistemi in pompa di calore idronici e i sistemi ad espansione diretta residenziali. Il corso affronta il dimensionamento degli accumuli sanitari abbinati alla pompa di calore, l''implementazione impiantistica nelle riqualificazioni e nelle nuove realizzazioni, la valutazione degli impianti esistenti e gli incentivi fiscali disponibili. Corso svolto presso la sala Campus di IDEMA CLIMA.',
    NULL, NULL, NULL, 1, 0, NULL, 1, 0, 70
  )
ON DUPLICATE KEY UPDATE
  title = VALUES(title),
  audience = VALUES(audience),
  category = VALUES(category),
  location = VALUES(location),
  address = VALUES(address),
  starts_at = VALUES(starts_at),
  ends_at = VALUES(ends_at),
  short_description = VALUES(short_description),
  description = VALUES(description),
  registration_open = VALUES(registration_open),
  published = VALUES(published),
  cancelled = VALUES(cancelled),
  sort_order = VALUES(sort_order);
