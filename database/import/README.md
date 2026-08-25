# Import archivio tecnico Lovable

Questa cartella contiene l'importatore **una tantum** del dataset `src/data/datasheets.ts` del progetto Lovable verso il database autonomo IDEMA.

## Principi

- Il repository Lovable è sempre trattato come **sorgente in sola lettura**.
- L'importatore non scrive sul sito `idemaclima.it` e non modifica il progetto Lovable.
- L'esecuzione predefinita è **dry-run**: nessuna scrittura MySQL avviene senza `--execute`.
- Categorie, prodotti, modelli e documenti vengono creati in modo idempotente.
- Le etichette dei manuali (`INSTALLAZIONE`, `USO`, telecomandi, Wi-Fi) non vengono erroneamente trasformate in modelli prodotto.
- URL e asset Lovable/originali vengono inizialmente conservati come `file_path`; la copia fisica dei PDF nel nuovo archivio sarà una fase successiva e separata.
- Le integrazioni non presenti come record autonomi nel dataset Lovable sono ammesse solo se documentate da fonti pubbliche originali IDEMA e registrate in `verified_component_models.json`.

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

## 6. Sequenza import reale

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

L'importatore delle combinazioni è separato perché deve poter collegare prodotti, modelli e PDF già presenti nel database. Se un target indicato nel manifest non viene trovato, l'item viene degradato a `pending` e il problema viene registrato nel report invece di creare automaticamente entità mancanti.

L'import reale del database non va eseguito finché i report di dry-run e normalizzazione non sono stati controllati.
