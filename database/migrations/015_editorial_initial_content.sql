INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Connessione dispositivi Android','android','Configurazione dell’app, associazione del climatizzatore alla rete Wi-Fi e gestione del dispositivo da smartphone o tablet Android.',10,1 FROM editorial_pages WHERE slug='configurazione-wi-fi';
INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Connessione dispositivi iOS','ios','Configurazione dell’app e procedura di associazione per dispositivi iPhone e iPad.',20,1 FROM editorial_pages WHERE slug='configurazione-wi-fi';
INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Domande e risposte utili','domande-risposte','Indicazioni operative su timer, rete wireless, modalità AP e condizioni necessarie per il corretto funzionamento del modulo Wi-Fi.',30,1 FROM editorial_pages WHERE slug='configurazione-wi-fi';
INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Risoluzione dei problemi','risoluzione-problemi','Controlli da effettuare quando l’associazione o il controllo remoto non funzionano correttamente.',40,1 FROM editorial_pages WHERE slug='configurazione-wi-fi';
INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Configurazione per Skill Alexa','alexa','Indicazioni per collegare i dispositivi compatibili al controllo vocale tramite Amazon Alexa.',50,1 FROM editorial_pages WHERE slug='configurazione-wi-fi';

INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Bonus Casa','bonus-casa','Agevolazioni per interventi edilizi e lavori di ristrutturazione che possono comprendere sistemi di climatizzazione quando ricorrono i requisiti previsti dalla normativa.',10,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi';
INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Ecobonus','ecobonus','Detrazioni collegate agli interventi di riqualificazione ed efficientamento energetico degli edifici.',20,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi';
INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Conto Termico','conto-termico','Contributo dedicato a specifici interventi di efficienza energetica e produzione di energia termica da fonti rinnovabili. Consulta la pagina dedicata per i dettagli.',30,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi';
INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Libretto d’impianto','libretto-impianto','Informazioni generali su compilazione, responsabilità, controlli periodici e possibili differenze regionali. I requisiti normativi devono essere verificati al momento dell’intervento.',40,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi';

INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Chi può accedere','accesso','Il Conto Termico prevede modalità di accesso differenti per soggetti privati e pubbliche amministrazioni, secondo la disciplina vigente.',10,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi/conto-termico';
INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Interventi incentivabili','interventi','Tra gli interventi ammessi possono rientrare, quando rispettano i requisiti applicabili, sostituzioni di impianti con pompe di calore e altre soluzioni ad alta efficienza.',20,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi/conto-termico';
INSERT INTO editorial_sections (page_id,title,anchor_slug,body,sort_order,published)
SELECT id,'Erogazione dell’incentivo','erogazione','Importi, percentuali, tempi e modalità di erogazione dipendono dalla disciplina vigente e dalle caratteristiche dell’intervento.',30,1 FROM editorial_pages WHERE slug='detrazioni-e-incentivi/conto-termico';

INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'Chi può installare i climatizzatori?','L’installazione deve essere affidata a un’impresa in possesso delle abilitazioni e certificazioni richieste dalla normativa applicabile, comprese quelle relative ai gas fluorurati quando previste.',10,1 FROM editorial_pages WHERE slug='faq';
INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'Che cosa copre la garanzia di un climatizzatore?','La garanzia copre i difetti previsti dalle condizioni applicabili al prodotto. Non sostituisce la responsabilità per problemi dovuti a installazione non corretta, uso improprio o mancata manutenzione.',20,1 FROM editorial_pages WHERE slug='faq';
INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'Perché è importante dimensionare correttamente il climatizzatore?','La potenza necessaria dipende da volume dei locali, esposizione, isolamento, carichi interni e condizioni d’uso. Il dimensionamento deve quindi essere valutato da un tecnico.',30,1 FROM editorial_pages WHERE slug='faq';
INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'Perché l’unità esterna può vibrare?','Le vibrazioni possono dipendere da supporti, livellamento, staffe, antivibranti o condizioni di manutenzione. È opportuno verificare l’installazione e lo stato dell’unità.',40,1 FROM editorial_pages WHERE slug='faq';
INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'È possibile installare un’unità esterna sul tetto?','È possibile quando l’installazione rispetta i requisiti tecnici e consente l’accesso in sicurezza per manutenzione e assistenza.',50,1 FROM editorial_pages WHERE slug='faq';
INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'Che cos’è una pompa di calore?','È un sistema che trasferisce energia termica da una sorgente a temperatura più bassa a un ambiente a temperatura più alta, utilizzando energia per il funzionamento del ciclo frigorifero.',60,1 FROM editorial_pages WHERE slug='faq';
INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'Cosa indicano i BTU/h?','I BTU/h esprimono la potenza frigorifera. Per il dimensionamento tecnico si utilizza normalmente anche il kW.',70,1 FROM editorial_pages WHERE slug='faq';
INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'Quando bisogna ricaricare il gas refrigerante?','Il circuito frigorifero è chiuso: una perdita di refrigerante richiede l’individuazione e la riparazione della causa, non una ricarica periodica programmata.',80,1 FROM editorial_pages WHERE slug='faq';
INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'Quale temperatura è consigliabile impostare?','Il comfort dipende dalle condizioni ambientali. In raffrescamento è consigliabile evitare differenze eccessive tra temperatura interna ed esterna e utilizzare la deumidificazione quando utile.',90,1 FROM editorial_pages WHERE slug='faq';
INSERT INTO faq_items (page_id,question,answer,sort_order,published)
SELECT id,'Quanto è importante la manutenzione?','Pulizia dei filtri e controlli periodici aiutano a mantenere efficienza, qualità dell’aria e affidabilità del sistema nel tempo.',100,1 FROM editorial_pages WHERE slug='faq';
