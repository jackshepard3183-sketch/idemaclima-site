# Pipeline sicura di import IDEMA

Questa procedura sostituisce l'uso diretto di import misti rete+filesystem+database durante la prima migrazione reale.

## 1. Prodotti storici: preflight DB read-only

Prima di qualsiasi `--execute`:

```bash
php database/import/preflight_historical_products.php
php database/import/preflight_historical_products.php --registry=terminali_idronici_registry.json
```

Il preflight:

- non esegue INSERT/UPDATE/DELETE;
- verifica categorie padre;
- verifica categorie secondarie `also_category_slugs`;
- accetta slug prodotto già presenti come caso idempotente;
- intercetta codici modello già presenti sotto un altro prodotto;
- genera `database/import/reports/preflight-<registry>-latest.json`;
- termina con exit code 2 se esistono conflitti bloccanti.

Solo dopo un preflight pulito:

```bash
php database/import/import_historical_products.php --execute
php database/import/import_historical_products.php --registry=terminali_idronici_registry.json --execute
```

## 2. PDF: separare copia fisica e scrittura DB

### 2.1 Download, validazione e SHA-256

```bash
php database/import/migrate_documents.php --manifest=document_migration_manifest.csv --download
php database/import/migrate_documents.php --manifest=supplemental_document_manifest.csv --download
```

Questa fase copia/verifica i PDF nello storage locale ma non deve essere usata per scrivere il database.

### 2.2 Preflight DB dei file già copiati

```bash
php database/import/import_downloaded_documents.php --manifest=document_migration_manifest.csv
php database/import/import_downloaded_documents.php --manifest=supplemental_document_manifest.csv
```

Il preflight verifica prima di aprire una transazione:

- presenza di ogni PDF locale;
- firma `%PDF-`;
- limite 50 MB;
- SHA-256 ricalcolato dal file locale;
- esistenza `document_types`;
- esistenza categorie;
- esistenza prodotti;
- esistenza del modello sotto il prodotto corretto;
- coerenza dei collegamenti CE.

Se anche una sola riga non è valida, non viene eseguita alcuna scrittura DB.

### 2.3 Import DB transazionale

Solo dopo preflight pulito:

```bash
php database/import/import_downloaded_documents.php --manifest=document_migration_manifest.csv --execute
php database/import/import_downloaded_documents.php --manifest=supplemental_document_manifest.csv --execute
```

Tutte le scritture del singolo manifest avvengono nella stessa transazione MySQL. In caso di errore:

- viene eseguito `ROLLBACK`;
- nessuna riga parziale del manifest rimane nel DB;
- i PDF già copiati sul filesystem restano disponibili per correggere il problema e riprovare senza riscaricarli.

La deduplica DB continua a usare `documents.sha256`; gli URL sorgente sono conservati in `document_source_aliases`.

## 3. Prodotti in più linee

`products.category_id` resta la categoria primaria.

`product_category_links` aggiunge le categorie secondarie. Lo stesso prodotto può quindi comparire, per esempio, sia in Multi Split sia in Commerciale senza duplicare:

- prodotto;
- modelli;
- PDF;
- statistiche download.

Il registry usa `also_category_slugs` per queste relazioni.

## 4. Test

```bash
bash tests/run_all.sh
```

La suite comprende `migration_pipeline_smoke.php`, che controlla la presenza delle protezioni fondamentali della pipeline.

## Ordine operativo obbligato prima dello staging

1. applicare tutte le migration, compresa `017_product_category_links.sql`;
2. import Lovable base;
3. import modelli verificati e combinazioni;
4. eseguire preflight read-only dei registry storici;
5. risolvere eventuali conflitti;
6. importare i prodotti storici;
7. scaricare e verificare fisicamente i PDF;
8. eseguire preflight DB dei PDF locali;
9. importare i documenti con `import_downloaded_documents.php --execute`;
10. verificare schede tecniche, alias e redirect su staging;
11. solo dopo pianificare il go-live.

Il sito pubblico `idemaclima.it` e il progetto Lovable restano sorgenti in sola lettura durante tutta questa procedura.
