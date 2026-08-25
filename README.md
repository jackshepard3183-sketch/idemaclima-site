# IDEMA Clima - sito autonomo

Scaffold del nuovo sito indipendente da Lovable/Supabase. Il progetto è pensato per PHP 8.2+ e MySQL/MariaDB su hosting standard.

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

## Backend disponibile nello scaffold
- autenticazione amministrativa con password hash PHP e sessione server-side;
- CSRF su login/logout;
- dashboard con conteggi principali;
- gestione categorie, prodotti, modelli e documenti;
- runner migration con checksum per impedire modifiche accidentali a migration già eseguite;
- audit log delle operazioni amministrative.

## Import archivio tecnico Lovable

È disponibile un importatore CLI in `database/import/import_datasheets.php`.

L'importatore legge `src/data/datasheets.ts` in sola lettura e, per sicurezza, parte sempre in **dry-run**. Il parametro `--execute` abilita esplicitamente la scrittura sul nuovo database MySQL.

Prima dell'import reale viene prodotto un report JSON con i casi che richiedono normalizzazione (per esempio combinazioni di unità o etichette ambigue).

Test rapidi:

```bash
./tests/import_datasheets_smoke.sh
./tests/php_syntax.sh
```

Nessuna dipendenza runtime dal progetto Lovable o dal sito pubblico.
