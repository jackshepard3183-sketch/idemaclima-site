# IDEMA Clima – confronto preliminare documenti storico vs Lovable

Data: 2026-08-26

## Stato del confronto

Il manifest curato `document_migration_manifest.csv` contiene ora **23 URL documentali storici verificati o estratti direttamente dalle pagine pubbliche IDEMA**.

Composizione attuale:
- 8 Dichiarazioni CE;
- documentazione Mini Chiller R32 (scheda tecnica, detrazioni, Conto Termico, manuale, comando remoto, I-TANK-75R);
- documentazione Recuperatori IDHR / URC80-EC;
- manuale purificatore FTXM-740XIT;
- manuale barriera AC-SA1;
- 3 PDF Distribuzione aria.

Questo non è ancora l'inventario completo del sito storico: mancano ancora alcuni link non risolti durante il fetch (es. AC-RE e modulo richiesta plenum) e devono essere censite in modo sistematico tutte le pagine sorgente.

## Verifica diretta nel repository Lovable

Sono state effettuate ricerche per filename esatto nel repository `jackshepard3183-sketch/idemaclima` per alcuni file storici ad alto valore. Non sono risultati match per:

- `REF_MINI_CHILLER_IGC_DC_INVERTER_R32.pdf`
- `TAB_IDHR_2024.pdf`
- `UM_FTXM-740XIT.pdf`
- `SISTEMI-EASY-KIT-ZONE-2022.pdf`

Questa verifica indica che questi file sono **forti candidati `historical_only`**, ma non sostituisce il confronto parser-to-parser su tutto `datasheets.ts`: la ricerca GitHub per filename non è considerata prova esaustiva.

## Principio di copia fisica

La copia fisica resta raccomandata per garantire indipendenza dal vecchio WordPress e da Lovable.

Regole:
1. ogni URL storico viene conservato come alias sorgente;
2. il file viene scaricato e validato;
3. viene calcolato SHA-256;
4. file con SHA-256 identico condividono una sola copia fisica;
5. più URL legacy possono quindi puntare allo stesso `documents.id`;
6. i vecchi URL possono essere reindirizzati alla nuova risorsa locale dopo il go-live.

## File fisicamente verificati via sorgente pubblica

Sono stati risolti e letti con successo, tra gli altri:

- `IM-UM_REF_MINI_CHILLER_IGC_DC_INVERTER_R32.pdf`
- `REF_COMANDO_REMOTO_KJRH-120K-BMKO-E.pdf`
- `I-TANK-75R.pdf`
- `UM_IDHR-WIFI.pdf`
- `VMC_URC80-EC.pdf`
- `UM_FTXM-740XIT.pdf`
- `IM_AC-SA1.pdf`
- `SISTEMI-EASY-KIT-ZONE-2022.pdf`
- `SISTEMA-RADIO-2022.pdf`
- `COMPONENTI-DISTRIBUZIONE-ARIA-2022.pdf`

Alcuni URL risultano presenti nelle pagine ma protetti da challenge/cache durante la verifica automatizzata; devono comunque restare nel manifest e verranno validati dalla pipeline di migrazione in staging.

## Nuovo piano candidate-copy

Lo script `build_unique_document_manifest.php` raggruppa il manifest per filename e genera il piano candidato di copie fisiche.

La deduplica per filename è solo preliminare: **SHA-256 è sempre l'autorità finale**. Due file con nomi uguali ma contenuto diverso restano distinti; due file con nomi diversi ma contenuto identico vengono unificati.

## Prossimo criterio di completamento

L'inventario sarà considerato pronto alla copia reale quando:

- tutte le pagine sorgente tecniche/cataloghi sono state attraversate;
- ogni link PDF storico è nel report deduplicato per URL;
- tutti i 757 URL unici Lovable sono stati confrontati;
- ogni riga ha stato `historical_only`, `lovable_only`, `both` o `review`;
- i casi `review` sono risolti o esplicitamente approvati;
- il piano fisico è deduplicabile tramite SHA-256 senza collisioni logiche.
