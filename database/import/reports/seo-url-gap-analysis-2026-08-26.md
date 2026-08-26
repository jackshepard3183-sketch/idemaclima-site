# IDEMA Clima – SEO URL gap analysis

Data analisi: 2026-08-26

## Obiettivo
Confrontare il sito pubblico storico `www.idemaclima.it` con l'architettura del nuovo sito autonomo e con il dataset Lovable già normalizzato, individuando contenuti/URL che non devono andare persi in migrazione.

## Principio di migrazione
Il dataset Lovable `datasheets.ts` non è sufficiente come unica fonte SEO/storica. Il sito originale contiene pagine informative, famiglie storiche, prodotti fuori catalogo e documentazione indicizzata che devono essere preservati tramite import o redirect.

## URL pubblici ad alta priorità da preservare o ricreare

| URL storico | Azione target | Stato |
|---|---|---|
| `/schede-tecniche/` | mantenere come hub | già previsto |
| `/cataloghi/` | mantenere | già previsto |
| `/assistenza/` | ricreare pagina dedicata | mancante |
| `/faq/` | ricreare pagina FAQ | mancante |
| `/configurazione-wi-fi/` | ricreare guida dedicata | mancante |
| `/detrazioni-e-incentivi/` | ricreare hub incentivi | mancante |
| `/detrazioni-e-incentivi/conto-termico/` | ricreare contenuto | mancante |
| `/schede-tecniche/dichiarazioni-conformita-ce/` | preservare come raccolta documenti | mancante come pagina dedicata |
| `/galleria/` | mantenere | già previsto |
| `/referenze/` | mantenere | già previsto |
| `/garanzia/` | mantenere | già previsto |
| `/campus/` | mantenere | già previsto |
| `/contatti/` | mantenere | già previsto |

## Gap nelle Schede Tecniche rispetto al sito originale

Il sito originale espone contenuti storici non interamente rappresentati nel dataset Lovable normalizzato. Esempi verificati:

- `Altri prodotti` include anche **Purificatori d'aria** e **Barriere a lama d'aria**, oltre ai recuperatori.
- storico VRF include famiglie come **VRF Individuali** e **VRF V5** con prodotti R410A fuori catalogo.
- area idronica storica include **Refrigeratori / Chiller modulari**.
- hub Schede Tecniche storico include anche **Distribuzione aria / kit completi**.
- pagina separata per **Dichiarazioni di Conformità CE** per linee prodotto e accessori.

Questi contenuti vanno mantenuti come archivio storico ricercabile, marcati `discontinued` o `unavailable` quando appropriato, non eliminati.

## Gap informativi

### Assistenza
Il sito originale presenta una pagina Assistenza che collega FAQ, richiesta assistenza, termini di garanzia e codici di errore. Nel nuovo sito manca ancora una pagina editoriale equivalente.

### FAQ
Il sito originale contiene una raccolta FAQ con contenuti informativi/SEO. Va migrata in una struttura editoriale amministrabile, non hardcoded.

### Configurazione Wi-Fi
La pagina originale contiene una guida molto estesa per Android/iOS, Bluetooth, modalità AP, troubleshooting e Alexa. Va preservata come contenuto dedicato; non è sufficiente avere solo i manuali PDF dei moduli Wi-Fi.

### Detrazioni e incentivi
Il sito originale dispone di un hub dedicato e di pagine specifiche (es. Conto Termico). Questi contenuti hanno valore SEO e informativo e non devono essere sostituiti soltanto dai PDF collegati ai prodotti.

## Cataloghi
Il sito originale mostra cataloghi correnti e monografici, tra cui catalogo generale, Multi Pro, Atomix, VRF, idronico, dimensionali e brochure prodotto. Il nuovo modulo Cataloghi è già adatto a riceverli, ma sarà necessario un import completo dei PDF/copertine storiche e correnti.

## Strategia URL Schede Tecniche

Per le nuove URL pubbliche usare una struttura stabile:

- `/schede-tecniche/{gamma}`
- `/schede-tecniche/famiglia/{slug}`
- `/schede-tecniche/prodotto/{slug}`

Per ogni URL storico profondo WordPress:
1. se il contenuto continua a esistere come famiglia/prodotto, creare redirect 301 alla risorsa più specifica;
2. se è storico/fuori catalogo, importarlo e reindirizzare alla nuova pagina archivio equivalente;
3. evitare redirect massivi alla home o all'hub Schede Tecniche quando esiste una destinazione semantica più precisa.

## PDF storici
Gli URL `/wp-content/uploads/.../*.pdf` risultano ancora indicizzati. Prima del go-live:

- copiare i PDF utili nel nuovo storage;
- mantenere i documenti storici collegati ai modelli/prodotti;
- creare redirect 301 per i PDF che cambiano percorso;
- non eliminare documenti fiscali/Conto Termico/CE ancora utili senza una sostituzione equivalente.

## Priorità implementative emerse

1. creare modulo/pagine editoriali per Assistenza, FAQ, Wi-Fi, Detrazioni/Incentivi;
2. estendere import storico delle Schede Tecniche oltre `datasheets.ts`;
3. importare Dichiarazioni CE come documenti categorizzati + pagina raccolta;
4. aggiungere famiglie/prodotti storici mancanti (purificatori, barriere aria, VRF storici, chiller, distribuzione aria);
5. produrre inventario URL storico completo e tabella redirect 1:1;
6. importare cataloghi e PDF storici/correnti;
7. solo dopo congelare sitemap e redirect di produzione.

## Regola di sicurezza SEO
Nessun redirect storico va attivato sul dominio pubblico finché il target non è disponibile nello staging e verificato con risposta 200.