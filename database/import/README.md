# Import archivio tecnico Lovable

Questa cartella contiene l'importatore **una tantum** del dataset `src/data/datasheets.ts` del progetto Lovable verso il database autonomo IDEMA.

## Principi

- Il repository Lovable è sempre trattato come **sorgente in sola lettura**.
- L'importatore non scrive sul sito `idemaclima.it` e non modifica il progetto Lovable.
- L'esecuzione predefinita è **dry-run**: nessuna scrittura MySQL avviene senza `--execute`.
- Categorie, prodotti, modelli e documenti vengono creati in modo idempotente.
- Le etichette dei manuali (`INSTALLAZIONE`, `USO`, telecomandi, Wi-Fi) non vengono erroneamente trasformate in modelli prodotto.
- Le combinazioni tipo `IQZI-35-R32 + IOZ-35M-R32` non vengono forzate nel database: finiscono nella coda di normalizzazione del report.
- URL e asset Lovable/originali vengono inizialmente conservati come `file_path`; la copia fisica dei PDF nel nuovo archivio sarà una fase successiva e separata.

## Uso

Dry-run:

```bash
php database/import/import_datasheets.php --source=/path/to/datasheets.ts
```

Import reale (solo dopo aver applicato migration e configurato `.env`):

```bash
php database/import/import_datasheets.php --source=/path/to/datasheets.ts --execute
```

Report personalizzato:

```bash
php database/import/import_datasheets.php \
  --source=/path/to/datasheets.ts \
  --report=/tmp/idemaclima-import-report.json
```

Il report contiene statistiche, warning e una `normalization_queue` con i casi da verificare prima della migrazione definitiva.
