# Import archivio tecnico Lovable

Questa cartella contiene gli importatori **una tantum** del dataset `src/data/datasheets.ts` del progetto Lovable e dello storico verificato del sito originale verso il database autonomo IDEMA.

## Principi

- Il repository Lovable è sempre trattato come **sorgente in sola lettura**.
- Il sito pubblico `idemaclima.it` è usato esclusivamente come fonte storica/SEO in sola lettura.
- Gli importatori non scrivono sul sito `idemaclima.it` e non modificano il progetto Lovable.
- L'esecuzione predefinita è **dry-run**: nessuna scrittura MySQL avviene senza `--execute`.
- Categorie, prodotti, modelli e documenti vengono creati in modo idempotente.
- Le etichette dei manuali (`INSTALLAZIONE`, `USO`, telecomandi, Wi-Fi) non vengono erroneamente trasformate in modelli prodotto.
- Le integrazioni non presenti come record autonomi nel dataset Lovable sono ammesse solo se documentate da fonti pubbliche originali IDEMA e registrate in un registry curato.

## 1. Dry-run archivio tecnico

```bash
php database/import/import_datasheets.php --source=/path/to/datasheets.ts
```

Il report contiene statistiche, warning e la coda dei casi non standard.

## 2. Import modelli componenti verificati

Il registry `verified_component_models.json` contiene i modelli unità interna confermati tramite fonti IDEMA e la loro serie di appartenenza.

Dry-run:

```bash
php database/import/import_verified_component_models.php
```

Import reale, solo dopo l'import base dell'archivio tecnico:

```bash
php database/import/import_verified_component_models.php --execute
```

Il registry può anche dichiarare serie mancanti nel dataset Lovable ma confermate da documentazione IDEMA. Al momento sono `ITX-R32` e `IFFN-R32`.

## 3. Normalizzazione combinazioni

Generare il manifest base:

```bash
php database/import/normalize_combinations.php \
  --source=/path/to/datasheets.ts \
  --output=/tmp/idemaclima-combinations.json
```

Il normalizzatore usa esclusivamente match deterministici, case-insensitive, senza fuzzy matching.

Per i nomi prodotto sono ammessi solo alias ricavabili senza interpretazione:

- nome originale;
- alternative complete separate da ` / `;
- forme compatte come `105M/T` -> `105M` e `105T`;
- liste numeriche di capacità quando prefisso e suffisso restano invariati.

Ogni componente di una combinazione conserva sempre `raw_code`.

Stati item:

- `resolved_product`: collegamento certo a un prodotto;
- `resolved_model`: collegamento certo a un modello;
- `pending`: nessun collegamento certo, codice originale preservato;
- `review`: più candidati possibili.

Stati combinazione:

- `resolved`;
- `partial`;
- `pending`;
- `review`.

**Regola di sicurezza:** un componente `pending` non genera automaticamente un nuovo prodotto o modello.

## 4. Applicazione registry verificato al manifest

Dopo aver creato i modelli verificati, applicare il registry al manifest base:

```bash
php database/import/apply_verified_models_to_combinations.php \
  --manifest=/tmp/idemaclima-combinations.json \
  --output=/tmp/idemaclima-combinations-verified.json
```

Questo passaggio trasforma in `resolved_model` solo i `raw_code` presenti esplicitamente nel registry verificato. La fonte di verifica resta annotata nel manifest.

Il report del 26/08/2026 indica come esito atteso:

- 128 combinazioni contestualizzate;
- 256 componenti;
- 128 componenti risolti come prodotto;
- 128 componenti risolti come modello;
- 0 pending;
- 0 review.

## 5. Schema database combinazioni

Le tabelle base `product_combinations` e `product_combination_items` sono definite in `002_documents_relations.sql`.
La migration `006_combination_normalization.sql` aggiunge:

- prodotto sorgente e chiave sorgente della combinazione;
- stato di normalizzazione;
- possibilità per un item di puntare a `product_id` oppure `model_id`;
- `raw_code` per i componenti non risolti;
- relazione dedicata tra combinazioni e documenti PDF.

## 6. Sequenza import Lovable reale

Solo dopo aver applicato le migration, configurato `.env` e verificato tutti i report:

```bash
# 1. Archivio tecnico base
php database/import/import_datasheets.php --source=/path/to/datasheets.ts --execute

# 2. Serie/modelli verificati mancanti dal parsing base
php database/import/import_verified_component_models.php --execute

# 3. Generazione manifest combinazioni
php database/import/normalize_combinations.php \
  --source=/path/to/datasheets.ts \
  --output=/tmp/idemaclima-combinations.json

# 4. Applicazione dei modelli verificati
php database/import/apply_verified_models_to_combinations.php \
  --manifest=/tmp/idemaclima-combinations.json \
  --output=/tmp/idemaclima-combinations-verified.json

# 5. Verifica import combinazioni, senza scrivere
php database/import/import_combinations.php \
  --manifest=/tmp/idemaclima-combinations-verified.json

# 6. Import combinazioni
php database/import/import_combinations.php \
  --manifest=/tmp/idemaclima-combinations-verified.json \
  --execute
```

## 7. Archivio storico non presente in Lovable

`historical_products_registry.json` contiene esclusivamente categorie/prodotti/modelli verificati sul sito originale ma non affidabili come derivazione del solo `datasheets.ts`.

Stato iniziale del registry (26/08/2026):

- 9 categorie;
- 24 prodotti;
- 33 modelli;
- VRF Individuali e VRF V5;
- Mini Chiller e Chiller modulari;
- comandi/accessori refrigeratori;
- purificatore FTXM-740XIT;
- barriere AC-SA1 e AC-RE;
- Distribuzione aria.

Il crawler storico include inoltre le linee **Residenziale R410A** e **Commerciale R410A**, ancora pubblicate sul sito originale come archivio/fuori catalogo. Queste linee devono essere censite integralmente prima di congelare il registry prodotti definitivo.

Dry-run:

```bash
php database/import/import_historical_products.php
```

Import DB, **solo dopo l'import Lovable e dopo verifica conflitti**:

```bash
php database/import/import_historical_products.php --execute
```

L'importatore controlla i codici modello globalmente: se un modello storico esiste già sotto un altro prodotto, l'operazione viene interrotta e la transazione viene annullata. Non vengono creati doppioni automaticamente.

## 8. Scoperta ricorsiva dei documenti storici

`historical_document_sources.json` contiene gli hub di partenza. `discover_historical_documents.php` segue ricorsivamente le pagine figlie consentite delle aree tecniche/cataloghi, resta confinato a `idemaclima.it`, deduplica le pagine visitate e raccoglie soltanto i link PDF.

```bash
php database/import/discover_historical_documents.php
```

Output previsto:

`database/import/reports/historical-document-discovery-latest.json`

Questa fase è read-only rispetto al sito pubblico e non copia ancora i PDF nello storage autonomo.

## 9. Confronto WordPress storico ↔ Lovable

Confronto preliminare per filename:

```bash
php database/import/compare_document_archives.php \
  --lovable=/path/to/datasheets.ts \
  --historical=database/import/reports/historical-document-discovery-latest.json \
  --output=/tmp/document-comparison.csv
```

Il match per filename è solo un segnale preliminare. Non autorizza la deduplica fisica.

Per generare il manifest candidato finale e il registro alias:

```bash
php database/import/build_final_document_manifest.php \
  --historical=database/import/reports/historical-document-discovery-latest.json \
  --lovable=/path/to/datasheets.ts \
  --output=/tmp/final-document-manifest.csv \
  --aliases=/tmp/final-document-aliases.csv
```

Il primo CSV rappresenta i file fisici candidati; il secondo conserva tutti gli URL sorgente WordPress/Lovable. La risoluzione definitiva `1 file fisico ↔ N alias` avviene **solo dopo SHA-256**.

## 10. Migrazione fisica documenti

`document_migration_manifest.csv` è la lista curata dei PDF verificati da trasferire. Prima della copia reale deve essere sostituita/estesa dal manifest definitivo risultante dal confronto completo.

Solo controllo manifest, senza rete e senza DB:

```bash
php database/import/migrate_documents.php
```

Copia fisica e verifica dei PDF, **senza scrittura DB**:

```bash
php database/import/migrate_documents.php --download
```

Copia + inserimento documenti/collegamenti nel DB:

```bash
php database/import/migrate_documents.php --execute
```

Protezioni della migrazione documentale:

- sorgenti IDEMA consentite in HTTPS;
- massimo 4 redirect;
- verifica firma `%PDF-`;
- limite 50 MB;
- calcolo SHA-256;
- deduplica fisica per SHA-256;
- conservazione alias sorgente in `document_source_aliases`;
- dimensione e MIME del file persistiti nel record documento;
- non sovrascrive un PDF locale già presente senza verificarlo;
- report JSON in `database/import/reports/document-migration-latest.json`;
- i documenti CE vengono anche collegati alla pagina editoriale Dichiarazioni CE quando disponibile.

## 11. Test preventivi

```bash
php tests/historical_import_smoke.php
bash tests/run_all.sh
```

Lo smoke test verifica duplicati di slug categoria/prodotto, duplicati dei codici modello nel registry e duplicati/forme non valide degli URL nel manifest documenti.

## Ordine raccomandato complessivo

1. migration schema;
2. import Lovable base;
3. modelli verificati;
4. combinazioni;
5. crawl completo archivio storico;
6. confronto WordPress ↔ Lovable;
7. manifest finale file + alias;
8. dry-run archivio prodotti storico, incluse linee legacy R410A;
9. risoluzione conflitti modello;
10. import archivio storico;
11. `migrate_documents.php --download` e controllo SHA-256/report;
12. solo dopo, `migrate_documents.php --execute`;
13. verifica URL e redirect SEO su staging.

L'import reale del database non va eseguito finché i report di dry-run, confronto e normalizzazione non sono stati controllati.
