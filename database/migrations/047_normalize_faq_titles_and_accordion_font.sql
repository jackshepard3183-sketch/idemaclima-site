-- Uniforma i titoli delle FAQ alla forma frase, preservando sigle e nomi propri.
UPDATE faq_items f
JOIN editorial_pages p ON p.id = f.page_id
SET f.question = CASE f.sort_order
  WHEN 10 THEN 'Chi può installare i climatizzatori?'
  WHEN 20 THEN 'Che cosa copre la garanzia di un climatizzatore?'
  WHEN 30 THEN 'Calcolo della potenza necessaria del climatizzatore per un determinato locale: perché è importante?'
  WHEN 40 THEN 'Per fruire dell’Ecobonus cosa va inviato a ENEA? Come posso sapere se la mia domanda è stata accettata?'
  WHEN 50 THEN 'L’unità esterna emette forti vibrazioni'
  WHEN 60 THEN 'È possibile installare una unità esterna sopra il tetto?'
  WHEN 70 THEN 'Cos’è una pompa di calore?'
  WHEN 80 THEN 'Come si ottiene la deumidificazione dell’aria?'
  WHEN 90 THEN 'Cosa sono i BTU/h?'
  WHEN 100 THEN 'Cos’è la classe energetica dei climatizzatori?'
  WHEN 110 THEN 'Causa della perdita di gas refrigerante'
  WHEN 120 THEN 'Qual è la temperatura adatta da impostare?'
  WHEN 130 THEN 'Quando bisogna effettuare la ricarica del gas nel climatizzatore?'
  WHEN 140 THEN 'Il climatizzatore non raffredda o riscalda a sufficienza?'
  WHEN 150 THEN 'Durante i primi minuti di funzionamento in pompa calore il climatizzatore non emette aria calda?'
  WHEN 160 THEN 'Ho notato perdite di acqua dall’unità interna. Quale può essere la causa?'
  WHEN 170 THEN 'Ogni quanto serve pulire i filtri aria al climatizzatore?'
  WHEN 180 THEN 'Acquistare un climatizzatore di potenza adeguata'
  WHEN 190 THEN 'Posso “nascondere” il climatizzatore'
  WHEN 200 THEN 'Fare la manutenzione'
  ELSE f.question
END
WHERE p.slug = 'faq';
