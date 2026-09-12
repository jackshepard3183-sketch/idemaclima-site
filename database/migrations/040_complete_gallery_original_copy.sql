-- Completa esclusivamente i testi della Galleria con i contenuti del sito IDEMA originale.
UPDATE gallery_sections
SET eyebrow = '— Colorazioni ISAX-R32'
WHERE section_key = 'design';

UPDATE gallery_sections
SET description = 'Idema Clima ha da sempre la sua sede legale a Milano, ma il cuore pulsante della società è la sede operativa che è ubicata a Vertemate con Minoprio (CO) lungo la Strada Statale dei Giovi, in un moderno stabilimento su due piani in cui si trovano la Direzione, gli uffici amministrativi e commerciali, locali dedicati alla formazione ed all’esposizione dei prodotti oltre ad un ampio magazzino.\n\nIdema Clima ha infatti una nuovissima aula corsi dove vengono organizzate convention, seminari e corsi di aggiornamento tecnico-commerciali per agenti ed installatori ed uno show room tecnico ove chiunque può vedere, sentire e toccare con mano i modelli dei climatizzatori Idema.\n\nIdema Clima possiede un ulteriore magazzino, sede della ricambistica, dove, una struttura tecnica interna dedicata, si occupa anche dei test tecnici e di verifica qualità delle apparecchiature Idema; a pochi chilometri di distanza, invece, la Idema Clima si avvale di un’ampia logistica di oltre 4000 mq per lo stoccaggio del materiale.'
WHERE section_key = 'headquarters';

UPDATE gallery_content_items i
JOIN gallery_sections s ON s.id = i.section_id
SET i.description = CASE i.title
    WHEN 'Doppia Filtrazione' THEN 'Oltre al filtro purificatore standard, i modelli ISAX-R32 COLOR (Silver-Titanium-Black) offrono di serie una doppia filtrazione che migliora la qualità dell’aria rendendola fresca e salubre, garantendo un’atmosfera unica e di benessere negli ambienti chiusi. Il filtro agli Ioni d’argento elimina i batteri in modo efficace e continuo, mentre il filtro agli Ioni negativi impedisce la formazione di muffe, elimina cattivi odori, polvere e fumo.'
    WHEN 'Super Ionizzatore' THEN 'La tecnologia avanzata di questo filtro, presente di serie nei modelli ISAX-R32, genera milioni di ioni positivi e negativi per ogni m3 e così come succede in natura, quando gli ioni positivi incontrano gli ioni negativi, questo processo crea un’energia che elimina i batteri presenti nell’aria trasformandoli in molecole d’acqua innocue grazie ad una reazione chimica. Gli ioni negativi in eccesso rinfrescano l’aria rendendo l’ambiente di casa più salubre e confortevole.'
    WHEN 'Lampada Germicida' THEN 'I climatizzatori a parete sono soggetti alla formazione di muffa interna, in particolare in prossimità del ventilatore, inoltre, contaminanti biologici aerei, come germi, virus e allergeni, possono moltiplicarsi e diffondersi nel sistema di ventilazione. La lampada germicida SMUV-101 è dotata di 2 led UVC e 8 led UVA suddivisi su 2 strisce dalle dimensione sufficientemente ridotte (23 cm ciascuna) per essere inserite all’interno dell’unità a parete.\n\nUna soluzione unica, per l’eliminazione di virus e batteri, che migliora la qualità dell’aria.'
    ELSE i.description
END
WHERE s.section_key = 'air';

UPDATE gallery_content_items i
JOIN gallery_sections s ON s.id = i.section_id
SET i.description = CASE i.title
    WHEN 'ISAX-R32 Silver' THEN 'Uno Split raffinato ed eclettico, adatto a qualsiasi sfumatura di arredo e ad ogni interpretazione della casa: dai laboratori IDEMA nasce il nuovo look Silver, una livrea versatile e sempre elegante che trova il suo posto in soggiorni classici e moderni.'
    WHEN 'ISAX-R32 Titanium' THEN 'Uno Split dal carattere concreto, essenziale e raffinato: da IDEMA arriva il look Titanium, una livrea esclusiva disegnata ad hoc per integrarsi perfettamente negli ambienti hi-tech.'
    WHEN 'ISAX-R32 Black' THEN 'Uno Split elegante, di classe, pensato per un arredamento dal gusto moderno: IDEMA propone il nuovo look Black, un’esclusiva livrea estetica perfetta per inserirsi in un ambiente dallo stile minimalista.'
    ELSE i.description
END
WHERE s.section_key = 'design';

UPDATE gallery_content_items i
JOIN gallery_sections s ON s.id = i.section_id
SET i.description = 'Utilizzando la skill per Amazon Alexa e Google Home Assistant, puoi controllare, da remoto, tutte le principali funzionalità degli split IDEMA: è possibile accendere o spegnere il prodotto, modificare la modalità di funzionamento, impostare la temperatura e utilizzare le altre varie funzioni.\n\nTutti i climatizzatori a parete IDEMA, infatti, se dotati della funzione Wi-Fi (opzionale per alcune gamme) possono interfacciarsi e quindi esser comandati vocalmente attraverso i dispositivi Amazon e Google dotati di assistente intelligente.'
WHERE s.section_key = 'smart-home';

UPDATE gallery_content_items i
JOIN gallery_sections s ON s.id = i.section_id
SET i.description = 'IDEMA presenta il purificatore FTXM-740XIT, una soluzione per monitorare e rimuovere istantaneamente le impurità presenti nell’aria domestica, offrendo una depurazione costante, efficace e non invasiva. Ideale per ambienti con superficie fino a 85 m² (con una portata d’aria massima pari a 740 m³/h) e combina un sistema di filtrazione a 5 livelli con una tecnologia di sterilizzazione antibatterica agli ioni in grado di neutralizzare allergeni, spore e particelle virali, lasciando l’ambiente più sano.\n\nI sensori per il monitoraggio consentono di essere continuamente aggiornati sul livello di qualità dell’aria nell’ambiente, grazie a un rilevatore intelligente dedicato a gas, COV (composti organici volatili) e cattivi odori e un sensore apposito per le impurità particellari, tra cui PM2.5, polveri, pollini e peli di animali. Tramite l’azione combinata di tutti questi rilevatori, è in grado di effettuare una diagnostica completa in meno di un secondo.\n\nIn aggiunta alla silenziosità di funzionamento del ventilatore Inverter, può garantire una depurazione continuativa senza penalizzare la qualità del sonno. L’estetica, semplice e pulita, si unisce alle dimensioni compatte conferendo al purificatore d’aria un design adatto a qualsiasi ambiente domestico.'
WHERE s.section_key = 'purifier';
