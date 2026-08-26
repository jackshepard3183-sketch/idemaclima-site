# IDEMA Clima - sito autonomo

Nuovo sito indipendente da Lovable/Supabase, progettato per PHP 8.2+ e MySQL/MariaDB su hosting standard.

## Requisiti
- PHP 8.2+
- MySQL 8 / MariaDB 10.6+
- Estensioni PHP: pdo_mysql, mbstring, openssl, json, fileinfo

## Avvio locale
1. Copiare `.env.example` in `.env`
2. Configurare database e `APP_KEY`
3. Puntare il document root a `public/`
4. Eseguire le migration con `php scripts/migrate.php`
5. Creare il primo amministratore con `php scripts/create_admin.php --first-name=Nome --last-name=Cognome --email=mail@example.it --username=admin`
6. Aprire `/admin/login`

La password del primo admin può essere passata con `--password=...`; se omessa viene generata una password temporanea casuale e mostrata una sola volta nel terminale.

## Moduli implementati
- autenticazione amministrativa con password hash PHP, sessione server-side e CSRF;
- categorie, prodotti, modelli, documenti e combinazioni tecniche;
- Schede Tecniche pubbliche con ricerca e storico fuori catalogo;
- Garanzie con regole configurabili, snapshot storico e documenti privati;
- Campus pubblico e area CAT con account individuali, capienza e lista d'attesa;
- Cataloghi, Galleria e Referenze;
- Contatti con allegati privati e workflow amministrativo;
- Statistiche download PDF e configurazione GA4;
- Redirect SEO gestibili da backend e 404 pubblica;
- audit log delle operazioni amministrative.

## Sicurezza
- CSP, HSTS su HTTPS, X-Content-Type-Options, Referrer-Policy, X-Frame-Options e Permissions-Policy;
- upload pubblici con verifica MIME/contenuto e cartella non eseguibile;
- allegati sensibili fuori dalla web root in `storage/private/`;
- validazione server-side delle relazioni categoria/prodotto/modello;
- protezione da cicli nella gerarchia categorie;
- rate limiting server-side per login CAT, contatti, garanzie e iscrizioni Campus;
- nessun segreto o `.env` nel repository.

## Import archivio tecnico Lovable
È disponibile un importatore CLI in `database/import/import_datasheets.php`.

L'importatore legge `src/data/datasheets.ts` in sola lettura e, per sicurezza, parte sempre in **dry-run**. Il parametro `--execute` abilita esplicitamente la scrittura sul nuovo database MySQL.

Le combinazioni vengono normalizzate separatamente e i componenti verificati sono mantenuti in un registro versionato. La copia fisica dei PDF è una fase separata dall'import logico.

## Test
Esecuzione completa:

```bash
./tests/run_all.sh
```

Test disponibili singolarmente:

```bash
./tests/php_syntax.sh
php tests/router_regression.php
./tests/import_datasheets_smoke.sh
```

## Deploy
Prima del go-live:
1. creare ambiente staging con PHP/MySQL;
2. eseguire tutte le migration nell'ordine previsto;
3. importare dati tecnici in dry-run e poi in execute solo dopo controllo report;
4. migrare fisicamente PDF/immagini;
5. popolare redirect SEO dagli URL storici;
6. configurare GA4 e credenziali Google Data API;
7. eseguire `./tests/run_all.sh`;
8. verificare backup, HTTPS e header di sicurezza.

Nessuna dipendenza runtime dal progetto Lovable o dal sito pubblico.
