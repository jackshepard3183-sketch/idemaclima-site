<?php
$contactName=trim((string)($registration['customer_first_name']??'').' '.(string)($registration['customer_last_name']??''));
$contactSubject=preg_replace('/[\r\n]+/u',' ',(string)('Richiesta di garanzia '.(trim((string)($registration['certificate_number']??''))?:'#'.(int)$registration['id'])));
$contactEmail=trim((string)($registration['email']??''));
$contactDate=(string)($registration['created_at']??'');
$contactTimestamp=$contactDate!==''?strtotime($contactDate):false;
if($contactTimestamp!==false){
    $contactMonths=[1=>'gennaio',2=>'febbraio',3=>'marzo',4=>'aprile',5=>'maggio',6=>'giugno',7=>'luglio',8=>'agosto',9=>'settembre',10=>'ottobre',11=>'novembre',12=>'dicembre'];
    $contactDate=date('j',$contactTimestamp).' '.$contactMonths[(int)date('n',$contactTimestamp)].' '.date('Y',$contactTimestamp);
}
$warrantySummary=[];
foreach(['Prodotto'=>'product_name','Modello'=>'code','Data fattura'=>'invoice_date','Formula garanzia'=>'extension_formula'] as $label=>$field){
    $value=trim((string)($registration[$field]??''));
    if($value!=='')$warrantySummary[]=$label.': '.$value;
}
if(isset($registration['warranty_years']))$warrantySummary[]='Durata garanzia: '.(int)$registration['warranty_years'].' anni';
foreach(['Unità esterna'=>'outer_unit','Combinazione'=>'combination'] as $label=>$field){
    $value=trim((string)($details[$field]??''));
    if($value!=='')$warrantySummary[]=$label.': '.$value;
}
foreach($units as $unit){
    $unitLabel=($unit['unit_type']??'')==='outdoor'?'Unità esterna':((($unit['unit_type']??'')==='indoor')?'Unità interna':(string)($unit['unit_type']??'Unità'));
    $warrantySummary[]='Seriale '.$unitLabel.': '.(string)($unit['serial_number']??'');
}
if(!empty($registration['message']))$warrantySummary[]=(string)$registration['message'];
$contactOriginal="— Richiesta originale —\r\nDa: ".$contactName." — ".$contactEmail
    ."\r\nOggetto: ".$contactSubject
    .($contactDate!==''?"\r\nData: ".$contactDate:'')
    ."\r\n\r\n".str_replace(["\r\n","\r"],"\n",implode("\r\n",$warrantySummary));
$contactBody="\r\n\r\n".$contactOriginal;
$contactReplyUrl=filter_var($contactEmail,FILTER_VALIDATE_EMAIL)
    ?'mailto:'.rawurlencode($contactEmail).'?subject='.rawurlencode('Re: '.$contactSubject).'&body='.rawurlencode($contactBody)
    :'';
?>
<?php require __DIR__.'/_layout_start.php'; ?>
<?php $warrantyHeading=(string)($registration['certificate_number'] ?? 'IN VERIFICA'); ?>
<div class="toolbar"><div><h1>Garanzia <?= htmlspecialchars($warrantyHeading,ENT_QUOTES,'UTF-8') ?></h1><p class="muted"><?= htmlspecialchars($registration['product_name'].' - '.$registration['code']) ?></p></div><a class="btnlink" href="/idemaclima/admin/warranties">Torna alle registrazioni</a></div>
<div class="cards" style="margin-bottom:18px"><div class="panel"><strong>Cliente</strong><p><?= htmlspecialchars($registration['customer_last_name'].' '.$registration['customer_first_name']) ?><br><?= htmlspecialchars($registration['fiscal_code']) ?><br><?= htmlspecialchars($registration['email']) ?><?php if(!empty($registration['phone'])): ?><br><?= htmlspecialchars((string)$registration['phone']) ?><?php endif; ?></p></div><div class="panel"><strong>Indirizzo</strong><p><?= htmlspecialchars($registration['address']) ?><br><?= htmlspecialchars($registration['postal_code'].' '.$registration['city'].' ('.$registration['province'].')') ?><br><?= htmlspecialchars($registration['region']) ?></p></div><div class="panel"><strong>Garanzia</strong><p><?= $registration['warranty_years'] === null ? 'Da verificare' : (int)$registration['warranty_years'].' anni' ?><?php if($registration['extension_formula']): ?><br><?= htmlspecialchars($registration['extension_formula']) ?><?php endif; ?><br>Fattura: <?= htmlspecialchars($registration['invoice_date']) ?><?php if(array_key_exists('invoice_required_snapshot',$registration)): ?><br>Fattura richiesta: <?= (int)$registration['invoice_required_snapshot']===1?'Sì':'No' ?><?php endif; ?><?php if(array_key_exists('fgas_required_snapshot',$registration)): ?><br>F-GAS richiesto: <?= (int)$registration['fgas_required_snapshot']===1?'Sì':'No' ?><?php endif; ?><?php if(!empty($registration['reviewed_at'])): ?><br>Ultima verifica: <?= htmlspecialchars($registration['reviewed_at']) ?><?php endif; ?></p></div></div>
<div class="panel" style="margin-bottom:18px"><div style="display:flex;flex-wrap:wrap;gap:10px;margin:18px 0">
<?php if($contactReplyUrl!==''): ?><a class="btnlink" href="<?= htmlspecialchars($contactReplyUrl,ENT_QUOTES,'UTF-8') ?>">Contatta via email</a><?php endif; ?>
<button class="btn btn-secondary" type="button" id="contact-copy-request">Copia richiesta</button>
</div>
<p class="muted" id="contact-copy-status" role="status" aria-live="polite"></p>
<div id="contact-copy-fallback" hidden>
<label>Richiesta da copiare<textarea id="contact-original-text" readonly rows="8"><?= htmlspecialchars($contactOriginal,ENT_QUOTES,'UTF-8') ?></textarea></label>
</div>
<script nonce="<?= htmlspecialchars(\App\Core\Security::nonce(),ENT_QUOTES,'UTF-8') ?>">
(()=>{const button=document.getElementById('contact-copy-request'),text=document.getElementById('contact-original-text'),status=document.getElementById('contact-copy-status'),fallback=document.getElementById('contact-copy-fallback');button.addEventListener('click',async()=>{try{if(!navigator.clipboard?.writeText)throw new Error('clipboard');await navigator.clipboard.writeText(text.value);fallback.hidden=true;status.textContent='Richiesta copiata.';}catch(error){fallback.hidden=false;text.focus();text.select();status.textContent='Seleziona e copia il testo con Ctrl+C oppure con il comando Copia del dispositivo.';}});})();
</script>
</div>
<div class="panel" style="margin-bottom:18px"><h2>Unità registrate</h2><table><thead><tr><th>Tipo</th><th>Seriale</th></tr></thead><tbody><?php foreach($units as $u): ?><tr><td><?= htmlspecialchars($u['unit_type']) ?></td><td><?= htmlspecialchars($u['serial_number']) ?></td></tr><?php endforeach; ?></tbody></table></div>
<div class="panel" style="margin-bottom:18px">
<h2>Documenti privati</h2>
<?php if(!empty($_GET['document_replaced']) && in_array((string)$_GET['document_replaced'],['invoice','fgas'],true)): ?><p style="padding:12px;border-radius:8px;background:#dcfce7;color:#166534"><strong><?= $_GET['document_replaced']==='invoice'?'Fattura sostituita':'Documento F-GAS sostituito' ?> correttamente.</strong></p><?php endif; ?>
<p><?php if(!empty($registration['invoice_file'])): ?><a href="/idemaclima/admin/warranties/file/invoice/<?= (int)$registration['id'] ?>" target="_blank" rel="noopener">Apri fattura</a><?php else: ?><span class="muted">Fattura non allegata</span><?php endif; ?><?php if($registration['fgas_file']): ?> · <a href="/idemaclima/admin/warranties/file/fgas/<?= (int)$registration['id'] ?>" target="_blank" rel="noopener">Apri F-GAS</a><?php else: ?> · <span class="muted">F-GAS non allegato</span><?php endif; ?></p>
<div class="formgrid">
<form method="post" action="/idemaclima/admin/warranties/document/replace" enctype="multipart/form-data" onsubmit="return confirm('Sostituire la fattura attuale? Il file precedente verrà eliminato.');">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$registration['id'] ?>"><input type="hidden" name="kind" value="invoice">
<label>Sostituisci fattura<input type="file" name="replacement_file" accept="application/pdf,image/jpeg,image/png" required></label><button class="btn" type="submit">Carica nuova fattura</button>
</form>
<form method="post" action="/idemaclima/admin/warranties/document/replace" enctype="multipart/form-data" onsubmit="return confirm('Sostituire il documento F-GAS attuale? Il file precedente verrà eliminato.');">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$registration['id'] ?>"><input type="hidden" name="kind" value="fgas">
<label>Sostituisci F-GAS<input type="file" name="replacement_file" accept="application/pdf,image/jpeg,image/png" required></label><button class="btn" type="submit">Carica nuovo F-GAS</button>
</form>
</div><small class="muted" style="display:block;margin-top:10px">Formati consentiti: PDF, JPG e PNG, massimo 15 MB. I file restano nell’archivio privato e sono accessibili solo agli amministratori.</small>
</div>
<?php if(!empty($details)): ?><div class="panel" style="margin-bottom:18px"><h2>Sistema registrato</h2><p><strong>Tipologia:</strong> <?= htmlspecialchars(($details['product_type']??'')==='multi'?'Multi Split':'Mono Split') ?><br><?php if(!empty($details['outer_unit'])): ?><strong>Unità esterna:</strong> <?= htmlspecialchars((string)$details['outer_unit']) ?><br><?php endif; ?><strong>Combinazione:</strong> <?= htmlspecialchars((string)($details['combination']??'')) ?></p></div><?php endif; ?>
<div class="panel" style="margin-bottom:18px"><h2>Certificato di garanzia</h2><?php if(!empty($_GET['certificate_sent'])): ?><p style="padding:12px;border-radius:8px;background:#dcfce7;color:#166534"><strong>Certificato inviato al cliente e pratica contrassegnata come emessa.</strong></p><?php endif; ?><?php if($certificate): ?><p><strong><?= htmlspecialchars((string)$certificate['certificate_number']) ?></strong><br><span class="muted">Generato il <?= htmlspecialchars((string)$certificate['generated_at']) ?></span><?php if(!empty($certificate['sent_at'])): ?><br><strong>Inviato al cliente il <?= htmlspecialchars((string)$certificate['sent_at']) ?></strong><?php endif; ?></p><p><a class="btnlink" href="/idemaclima/admin/warranties/certificate/<?= (int)$registration['id'] ?>?v=<?= rawurlencode((string)$certificate['generated_at']) ?>" target="_blank" rel="noopener">Visualizza PDF</a></p><?php if(empty($certificate['sent_at']) && in_array($registration['status'],['approved','issued'],true) && filter_var($registration['email'],FILTER_VALIDATE_EMAIL)): ?><form method="post" action="/idemaclima/admin/warranties/certificate/send" style="margin-top:10px" onsubmit="return confirm('Inviare ora il certificato PDF all’indirizzo email del cliente?');"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$registration['id'] ?>"><button class="btn" type="submit">Invia certificato al cliente</button><small class="muted" style="display:block;margin-top:8px">L’invio imposterà automaticamente la pratica come “Certificato emesso”.</small></form><?php endif; ?><form method="post" action="/idemaclima/admin/warranties/certificate/generate" style="margin-top:10px"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$registration['id'] ?>"><button class="btn" type="submit">Rigenera PDF</button><small class="muted" style="display:block;margin-top:8px">La rigenerazione mantiene il numero del certificato, azzera l’eventuale data di invio e aggiorna layout e nome file.</small></form><?php elseif($registration['status']==='approved'): ?><p>La pratica è approvata. Puoi generare il certificato per il controllo interno.</p><form method="post" action="/idemaclima/admin/warranties/certificate/generate"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$registration['id'] ?>"><button class="btn" type="submit">Genera certificato PDF</button></form><?php else: ?><p class="muted">Approva prima la pratica per abilitare la generazione del certificato.</p><?php endif; ?></div>

<div class="panel" style="margin-bottom:18px">
<h2>Correggi dati della richiesta</h2>
<p class="muted">Il salvataggio invalida l’eventuale PDF esistente, che dovrà essere rigenerato prima dell’invio.</p>
<form method="post" action="/idemaclima/admin/warranties/update">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
<input type="hidden" name="id" value="<?= (int)$registration['id'] ?>">
<div class="formgrid">
<label>Modello<select name="model_id" required><?php foreach($models as $model): ?><option value="<?= (int)$model['id'] ?>" <?= (int)$registration['model_id']===(int)$model['id']?'selected':'' ?>><?= htmlspecialchars($model['product_name'].' - '.$model['code']) ?></option><?php endforeach; ?></select></label>
<label>Data fattura<input type="date" name="invoice_date" value="<?= htmlspecialchars((string)$registration['invoice_date']) ?>" required></label>
<label>Nome<input name="customer_first_name" maxlength="120" value="<?= htmlspecialchars((string)$registration['customer_first_name']) ?>" required></label>
<label>Cognome<input name="customer_last_name" maxlength="120" value="<?= htmlspecialchars((string)$registration['customer_last_name']) ?>" required></label>
<label>Codice fiscale / P.IVA<input name="fiscal_code" maxlength="32" value="<?= htmlspecialchars((string)$registration['fiscal_code']) ?>" required></label>
<label>Email<input type="email" name="email" maxlength="190" value="<?= htmlspecialchars((string)$registration['email']) ?>" required></label>
<label>Telefono<input name="phone" maxlength="50" value="<?= htmlspecialchars((string)$registration['phone']) ?>"></label>
<label>Indirizzo<input name="address" maxlength="255" value="<?= htmlspecialchars((string)$registration['address']) ?>" required></label>
<label>Regione<input name="region" maxlength="120" value="<?= htmlspecialchars((string)$registration['region']) ?>" required></label>
<label>Provincia<input name="province" maxlength="120" value="<?= htmlspecialchars((string)$registration['province']) ?>" required></label>
<label>CAP<input name="postal_code" maxlength="12" value="<?= htmlspecialchars((string)$registration['postal_code']) ?>" required></label>
<label>Città<input name="city" maxlength="120" value="<?= htmlspecialchars((string)$registration['city']) ?>" required></label>
<?php foreach($units as $u): ?><label><?= htmlspecialchars($u['unit_type']==='outdoor'?'Seriale unità esterna':'Seriale unità interna') ?><input name="unit_serials[<?= (int)$u['id'] ?>]" maxlength="160" value="<?= htmlspecialchars((string)$u['serial_number']) ?>" required></label><?php endforeach; ?>
<label>Tipologia sistema<select name="product_type"><option value="mono" <?= ($details['product_type']??'mono')==='mono'?'selected':'' ?>>Mono Split</option><option value="multi" <?= ($details['product_type']??'')==='multi'?'selected':'' ?>>Multi Split</option></select></label>
<label>Unità esterna / modello principale<input name="outer_unit" maxlength="80" value="<?= htmlspecialchars((string)($details['outer_unit'] ?: (($details['product_type']??'mono') === 'mono' ? ($details['combination']??$registration['code']) : ''))) ?>"></label>
<label class="full">Combinazione<input name="combination" maxlength="255" value="<?= htmlspecialchars((string)($details['combination']??$registration['code'])) ?>" required></label>
</div>
<button class="btn" type="submit">Salva correzioni</button>
</form>
</div>
<form class="panel" method="post" action="/idemaclima/admin/warranties/status"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$registration['id'] ?>"><div class="formgrid"><label>Stato<select name="status"><?php foreach(['pending'=>'In attesa','approved'=>'Approvata','rejected'=>'Rifiutata','issued'=>'Certificato emesso'] as $value=>$label): ?><option value="<?= $value ?>" <?= $registration['status']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select><small>“Certificato emesso” è accettato solo se il PDF è già stato generato e associato alla pratica.</small></label><label class="full">Note amministrative<textarea name="admin_notes" rows="4" maxlength="10000"><?= htmlspecialchars((string)$registration['admin_notes']) ?></textarea></label></div><button class="btn" type="submit">Salva stato</button></form>

<div class="panel" style="margin-top:18px"><h2>Storico pratica</h2><?php if($history): ?><div class="table-wrap"><table><thead><tr><th>Data</th><th>Attività</th><th>Dettagli</th></tr></thead><tbody><?php $historyLabels=['warranty.status'=>'Stato aggiornato','warranty.registration.update'=>'Dati della richiesta corretti','warranty.document.replace'=>'Documento privato sostituito','warranty.certificate.generate'=>'Certificato generato o rigenerato','warranty.certificate.send'=>'Certificato inviato al cliente'];foreach($history as $entry):$meta=json_decode((string)($entry['metadata']??''),true); ?><tr><td><?=htmlspecialchars(date('d/m/Y H:i',strtotime((string)$entry['created_at'])),ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars($historyLabels[$entry['action']]??(string)$entry['action'],ENT_QUOTES,'UTF-8')?></td><td><?php if(is_array($meta)&&$meta): ?><?=htmlspecialchars(implode(' · ',array_map(static fn($key,$value):string=>str_replace('_',' ',(string)$key).': '.(is_scalar($value)?(string)$value:'—'),array_keys($meta),array_values($meta))),ENT_QUOTES,'UTF-8')?><?php else: ?><span class="muted">—</span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="muted">Nessuna attività registrata per questa pratica.</p><?php endif; ?></div>

<div class="panel" style="margin-top:18px;border-color:#dc2626">
<h2 style="color:#b91c1c">Elimina richiesta</h2>
<p class="muted">Elimina definitivamente la pratica, il certificato e i documenti privati collegati.</p>
<form method="post" action="/idemaclima/admin/warranties/delete" onsubmit="return confirm('Eliminare definitivamente questa richiesta di garanzia? L’operazione non può essere annullata.');">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
<input type="hidden" name="id" value="<?= (int)$registration['id'] ?>">
<button class="btn" type="submit" style="background:#b91c1c">Elimina richiesta</button>
</form>
</div>
<?php require __DIR__.'/_layout_end.php'; ?>
