# Pipeline sicura di import IDEMA

Questa procedura separa rigorosamente configurazione ambiente, copia fisica dei file e scritture database. Il sito pubblico `idemaclima.it` e il progetto Lovable restano sorgenti in sola lettura fino al go-live.

## 0. Readiness dello staging

Prima di applicare migration o importare dati:

```bash
php scripts/staging_readiness.php
```

Il comando termina con exit code 2 se manca un requisito bloccante e produce `database/import/reports/staging-readiness-latest.json`.

Controlla almeno:

- PHP 8.2+;
- estensioni `pdo`, `pdo_mysql`, `fileinfo`, `json`, `openssl`, `mbstring`;
- `APP_ENV=staging` o `production`;
- `APP_URL` HTTPS;
- `APP_KEY` di almeno 32 caratteri;
- credenziali DB esplicite;
- directory private/upload/report scrivibili;
- numerazione migration senza duplicati;
- presenza degli importer critici;
- connessione MySQL/MariaDB reale.

`curl` è raccomandato ma non bloccante perché il downloader dispone di fallback stream HTTPS.

## 1. Migration

Applicare tutte le migration SQL in ordine numerico e registrare in staging quali file sono stati eseguiti. Non applicare migration direttamente in produzione come primo test.

La migration `018_warranty_hardening.sql` non crea un indice aggiuntivo su `certificate_number`: il vincolo `UNIQUE` definito nello schema originario è già sufficiente.

## 2. Prodotti Lovable e modelli

1. importare la base Lovable;
2. importare modelli verificati e combinazioni;
3. verificare i report prima di procedere allo storico.

## 3. Prodotti storici: preflight DB read-only

Prima di qualsiasi `--execute`:

```bash
php database/import/preflight_historical_products.php
php database/import/preflight_historical_products.php --registry=terminali_idronici_registry.json
```

Il preflight non esegue INSERT/UPDATE/DELETE e verifica:

- categorie padre;
- categorie secondarie `also_category_slugs`;
- ownership degli slug prodotto già presenti;
- codici modello già presenti sotto un altro prodotto.

Uno slug prodotto esistente è idempotente solo se la categoria primaria già presente è compatibile con la primaria/secondarie dichiarate nel registry. Gli altri casi sono `product_slug_conflict` bloccanti.

Solo dopo preflight pulito:

```bash
php database/import/import_historical_products.php --execute
php database/import/import_historical_products.php --registry=terminali_idronici_registry.json --execute
```

## 4. PDF: copia fisica separata dal DB

### 4.1 Download, validazione e SHA-256

```bash
php database/import/migrate_documents.php --manifest=document_migration_manifest.csv --download
php database/import/migrate_documents.php --manifest=supplemental_document_manifest.csv --download
```

Questa fase:

- accetta solo URL HTTPS sotto `www.idemaclima.it/wp-content/uploads/`;
- verifica firma `%PDF-` e limite 50 MB;
- calcola SHA-256;
- deduplica fisicamente i file;
- registra nel report `actual_filename`, `file_path` e SHA anche quando più URL condividono lo stesso binario.

**`migrate_documents.php --execute` è disabilitato.** La copia di rete/filesystem non può più scrivere direttamente nel database.

### 4.2 Preflight DB dei file già copiati

```bash
php database/import/import_downloaded_documents.php --manifest=document_migration_manifest.csv
php database/import/import_downloaded_documents.php --manifest=supplemental_document_manifest.csv
```

Il preflight usa il report del download per risolvere correttamente anche i file fisicamente deduplicati. Ricalcola comunque SHA-256 dal file locale e rifiuta una discrepanza tra report e filesystem.

Verifica inoltre:

- `document_types`;
- categorie;
- prodotti;
- modelli sotto il prodotto corretto;
- collegamenti CE.

Se anche una sola riga non è valida, non viene eseguita alcuna scrittura DB.

### 4.3 Import DB transazionale

Solo dopo preflight pulito:

```bash
php database/import/import_downloaded_documents.php --manifest=document_migration_manifest.csv --execute
php database/import/import_downloaded_documents.php --manifest=supplemental_document_manifest.csv --execute
```

Tutte le scritture del singolo manifest avvengono nella stessa transazione MySQL. In caso di errore viene eseguito `ROLLBACK`; i PDF già copiati restano sul filesystem per poter correggere e riprovare.

La deduplica DB usa `documents.sha256`; gli URL sorgente sono conservati in `document_source_aliases`.

## 5. Cataloghi

Dopo l'import dei documenti canonici:

```bash
php database/import/import_catalogs.php --preflight
php database/import/import_catalogs.php --execute
```

L'execute deve essere eseguito solo se tutti i PDF del registry Cataloghi sono già risolti nell'archivio documentale.

## 6. Prodotti in più linee

`products.category_id` resta la categoria primaria. `product_category_links` aggiunge le categorie secondarie. Lo stesso prodotto può quindi comparire in più linee senza duplicare prodotto, modelli, PDF o statistiche download.

## 7. Test applicativi

```bash
bash tests/run_all.sh
```

La suite statica/smoke deve essere eseguita prima dei test HTTP end-to-end. Non considerare lo staging pronto finché non sono stati eseguiti realmente sia `staging_readiness.php` sia `tests/run_all.sh` nel runtime PHP target.

## 8. Sequenza obbligata sul primo staging

1. clonare `idemaclima-site` in ambiente non pubblico;
2. configurare `.env` staging con URL HTTPS, APP_KEY e DB dedicato;
3. eseguire `php scripts/staging_readiness.php`;
4. applicare tutte le migration in ordine;
5. importare base Lovable;
6. importare modelli/combinazioni verificati;
7. preflight prodotti storici;
8. import prodotti storici;
9. scaricare/verificare PDF;
10. preflight DB documenti;
11. import DB documenti;
12. preflight/import Cataloghi;
13. importare o compilare contenuti rimanenti;
14. eseguire `bash tests/run_all.sh`;
15. test HTTP end-to-end di frontend, admin, Campus/CAT, Garanzia, Contatti, sitemap, redirect e download;
16. controllare alias storici e mapping SEO;
17. solo dopo preparare checklist di go-live e rollback.

Nessun passaggio di questa procedura autorizza modifiche al sito pubblico o al progetto Lovable.
