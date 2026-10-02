<?php
$contactName=trim((string)($row['first_name']??'').' '.(string)($row['last_name']??''));
$contactSubject=preg_replace('/[\r\n]+/u',' ',trim((string)($row['subject']??''))?:'Richiesta EasyTool');
$contactEmail=trim((string)($row['email']??''));
$contactDate=(string)($row['created_at']??'');
$contactTimestamp=$contactDate!==''?strtotime($contactDate):false;
if($contactTimestamp!==false){
    $contactMonths=[1=>'gennaio',2=>'febbraio',3=>'marzo',4=>'aprile',5=>'maggio',6=>'giugno',7=>'luglio',8=>'agosto',9=>'settembre',10=>'ottobre',11=>'novembre',12=>'dicembre'];
    $contactDate=date('j',$contactTimestamp).' '.$contactMonths[(int)date('n',$contactTimestamp)].' '.date('Y',$contactTimestamp);
}
$easyToolText=trim((string)($row['message']??''));
if($easyToolText===''){
    $easyToolDetails=[];
    foreach(['Azienda'=>'company','Telefono'=>'phone','CAP'=>'postal_code','Località'=>'city','Provincia'=>'province','Regione'=>'region','Profilo'=>'professional_role'] as $label=>$field){
        $value=trim((string)($row[$field]??''));
        if($value!=='')$easyToolDetails[]=$label.': '.$value;
    }
    $easyToolText=implode("\r\n",$easyToolDetails);
}
$contactOriginal="— Richiesta originale —\r\nDa: ".$contactName." — ".$contactEmail
    ."\r\nOggetto: ".$contactSubject
    .($contactDate!==''?"\r\nData: ".$contactDate:'')
    ."\r\n\r\n".str_replace(["\r\n","\r"],"\n",$easyToolText);
$contactBody="\r\n\r\n".$contactOriginal;
$contactReplyUrl=filter_var($contactEmail,FILTER_VALIDATE_EMAIL)
    ?'mailto:'.rawurlencode($contactEmail).'?subject='.rawurlencode('Re: '.$contactSubject).'&body='.rawurlencode($contactBody)
    :'';
?>
<?php require __DIR__.'/_layout_start.php'; ?>
<?php if(!empty($row['import_review_warning'])):?><div class="panel" style="margin-bottom:18px;border-color:#d97706"><strong>Da verificare</strong><p style="margin-bottom:0"><?= htmlspecialchars((string)$row['import_review_warning']) ?></p></div><?php endif;?>
<div class="toolbar"><div><h1>Richiesta #<?= (int)$row['id'] ?></h1><p class="muted">Ricevuta il <?= htmlspecialchars((string)$row['created_at']) ?></p></div><a class="btnlink" href="/idemaclima/admin/incentives<?= !empty($row['deleted_at'])?'?trash=1':'' ?>">Torna alle richieste</a></div>
<div class="cards" style="margin-bottom:18px"><div class="panel"><strong>Richiedente</strong><p><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?><br><?= htmlspecialchars((string)($row['company']??'')) ?></p></div><div class="panel"><strong>Contatti</strong><p><?= htmlspecialchars($row['email']) ?><br><?= htmlspecialchars($row['phone']) ?></p></div><div class="panel"><strong>Località</strong><p><?= htmlspecialchars($row['postal_code'].' '.$row['city'].' ('.$row['province'].')') ?><br><?= htmlspecialchars($row['region']) ?></p></div><div class="panel"><strong>Profilo</strong><p><?= htmlspecialchars($row['professional_role']) ?></p></div></div>
<div class="panel" style="margin-bottom:18px"><h3><?= htmlspecialchars($contactSubject,ENT_QUOTES,'UTF-8') ?></h3><p style="white-space:pre-wrap"><?= htmlspecialchars($easyToolText,ENT_QUOTES,'UTF-8') ?></p>
<div style="display:flex;flex-wrap:wrap;gap:10px;margin:18px 0">
<?php if($contactReplyUrl!==''): ?><a class="btnlink" href="<?= htmlspecialchars($contactReplyUrl,ENT_QUOTES,'UTF-8') ?>">Rispondi via email</a><?php endif; ?>
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
<?php if(empty($row['deleted_at'])): ?><form class="panel" method="post" action="/idemaclima/admin/incentives/update"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><div class="formgrid"><label>Stato<select name="status"><?php foreach(['new'=>'Nuova','in_progress'=>'In lavorazione','closed'=>'Chiusa','spam'=>'Spam'] as $value=>$label): ?><option value="<?= $value ?>" <?= $row['status']===$value?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label class="full">Note amministrative<textarea name="admin_notes" maxlength="10000"><?= htmlspecialchars((string)($row['admin_notes']??'')) ?></textarea></label></div><button class="btn" type="submit">Salva</button></form><?php else: ?><p class="panel">Questa richiesta è nel Cestino.</p><?php endif; ?>
<form method="post" action="/idemaclima/admin/incentives/<?= empty($row['deleted_at'])?'delete':'restore' ?>"<?= empty($row['deleted_at'])?' data-confirm="Spostare questa richiesta nel cestino? Potrai ripristinarla."':'' ?> style="margin-top:18px">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn btn-secondary" type="submit"><?= empty($row['deleted_at'])?'Elimina':'Ripristina' ?></button></form>
<?php if(!empty($row['deleted_at'])): ?><form method="post" action="/idemaclima/admin/incentives/purge" data-confirm="Eliminare definitivamente questa richiesta? Questa operazione non può essere annullata." style="margin-top:12px">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="confirm_permanent" value="1"><button class="btn btn-secondary" type="submit">Elimina definitivamente</button></form><?php endif; ?>
<?php require __DIR__.'/_layout_end.php'; ?>
