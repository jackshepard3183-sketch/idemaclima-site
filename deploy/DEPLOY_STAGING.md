# Deploy staging IDEMA Clima

Target di test:

`https://www.rappresentanzeguanzirolisas.it/idemaclima`

Questa procedura riguarda esclusivamente lo staging. Non modifica `idemaclima.it` né il progetto Lovable.

## 1. Prerequisiti hosting

- PHP 8.2+
- MySQL 8.0.16+ oppure MariaDB 10.2.1+
- HTTPS attivo
- mod_rewrite Apache disponibile
- estensioni PHP: pdo, pdo_mysql, fileinfo, json, openssl, mbstring
- possibilità di creare un database dedicato

## 2. Preparare il pacchetto

Dal repository:

```bash
bash scripts/build_staging_package.sh
```

Il comando crea:

- `build/idemaclima-staging/`
- se `zip` è disponibile, `build/idemaclima-staging.zip`

Il pacchetto non contiene un `.env` reale né dati privati.

## 3. Caricamento hosting

Creare la directory web:

`/idemaclima`

Caricare **il contenuto** della cartella `idemaclima-staging` dentro quella directory, mantenendo `.htaccess`.

La struttura attesa è, indicativamente:

```text
/idemaclima
  .htaccess
  app/
  config/
  database/
  deploy/
  public/
  scripts/
  storage/
  tests/
```

Non spostare `public/index.php` alla root: la `.htaccess` del progetto inoltra le richieste dinamiche a quel front controller.

## 4. Creare il database staging

Usare un database dedicato e vuoto. Non usare il database del sito `rappresentanzeguanzirolisas.it` e non usare eventuali database di produzione IDEMA.

Annotare:

- DB_HOST
- DB_PORT
- DB_DATABASE
- DB_USERNAME
- DB_PASSWORD

## 5. Creare `.env`

Copiare `deploy/.env.staging.example` in `.env` e sostituire esclusivamente i placeholder.

Configurazione base obbligatoria:

```env
APP_ENV=staging
APP_DEBUG=false
APP_URL=https://www.rappresentanzeguanzirolisas.it/idemaclima
APP_BASE_PATH=/idemaclima
APP_TIMEZONE=Europe/Rome
SITE_URL=https://www.rappresentanzeguanzirolisas.it/idemaclima
```

Generare `APP_KEY` casuale di almeno 32 caratteri. Non commetterla su GitHub.

## 6. Permessi

Devono essere scrivibili dall'utente PHP:

- `storage/private/`
- `public/uploads/`
- `database/import/reports/`

Evitare permessi 777 se non strettamente richiesti dal provider.

## 7. Primo controllo, nessuna scrittura schema

Da SSH/terminale, se disponibile:

```bash
bash scripts/staging_preflight.sh
```

Oppure singolarmente:

```bash
php scripts/staging_readiness.php
php scripts/migrate.php --status
bash tests/run_all.sh
```

`migrate.php --status` è read-only e non crea la tabella migration su un DB vuoto.

## 8. Applicare le migration

Solo se il preflight è pulito:

```bash
php scripts/migrate.php --execute
```

Subito dopo:

```bash
php scripts/migrate.php --status
```

Tutte le migration devono risultare APPLIED e senza checksum mismatch.

## 9. Verifica HTTP minima

Controllare almeno:

- `/idemaclima/`
- `/idemaclima/health`
- `/idemaclima/admin/login`
- `/idemaclima/schede-tecniche`
- `/idemaclima/sitemap.xml`
- `/idemaclima/robots.txt`

Lo staging deve inviare `X-Robots-Tag: noindex, nofollow, noarchive`.

## 10. Import dati

Dopo le migration seguire `database/import/SAFETY_PIPELINE.md` nell'ordine prescritto.

## 11. Rollback staging

Finché non è iniziato alcun go-live, il rollback dello staging consiste semplicemente in:

1. disabilitare/rinominare `/idemaclima`;
2. eliminare il database staging;
3. ricreare cartella e database da zero.

Non è necessario toccare il sito principale `rappresentanzeguanzirolisas.it`.
