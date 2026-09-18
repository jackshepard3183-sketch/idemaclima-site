<?php require __DIR__.'/_layout_start.php'; ?>
<?php $replySubject='Risposta alla richiesta IDEMA - '.(string)$row['subject'];$replyBody="Buongiorno ".(string)$row['first_name']." ".(string)$row['last_name'].",\n\nin merito alla sua richiesta inviata a Idema Clima:\n\n".(string)$row['message']."\n\nCordiali saluti\nIdema Clima S.r.l.";$replyMailto='mailto:'.(string)$row['email'].'?subject='.rawurlencode($replySubject).'&body='.rawurlencode($replyBody); ?>
<?php if(!empty($row['import_review_warning'])):?><div class="panel" style="margin-bottom:18px;border-color:#d97706"><strong>Da verificare</strong><p style="margin-bottom:0"><?= htmlspecialchars((string)$row['import_review_warning']) ?></p></div><?php endif;?>
<div class="toolbar"><h1>Richiesta contatto</h1><a class="btnlink" href="/idemaclima/admin/contacts">Torna all'elenco</a></div>
<div class="panel">
<p><strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></strong><br><strong>Profilo:</strong> <?= htmlspecialchars((string)($row['profile']??'')!==''?(string)$row['profile']:'Dato non disponibile') ?><br><?= htmlspecialchars($row['email']) ?><?= !empty($row['phone'])?' · '.htmlspecialchars($row['phone']):'' ?></p>
<p><?= htmlspecialchars($row['city']) ?> (<?= htmlspecialchars($row['province']) ?>) · CAP <?= htmlspecialchars((string)($row['postal_code']??'')) ?> · <?= htmlspecialchars($row['region']) ?></p>
<h3><?= htmlspecialchars($row['subject']) ?></h3><p style="white-space:pre-wrap"><?= htmlspecialchars($row['message']) ?></p>
<?php if(!empty($row['attachment_path'])): ?><p><a class="btnlink" href="/idemaclima/admin/contacts/file/<?= (int)$row['id'] ?>">Scarica allegato</a> <span class="muted"><?= htmlspecialchars((string)$row['attachment_name']) ?></span></p><?php endif; ?>
<div class="panel" style="margin:18px 0;background:var(--surface-soft,#f7faf7)">
<h3 style="margin-top:0">Risposta via email</h3>
<p><a class="btnlink" href="<?= htmlspecialchars($replyMailto,ENT_QUOTES,'UTF-8') ?>">Rispondi via email</a></p>
<?php if(!empty($row['reply_sent_at'])):?><p style="color:#3f7d0b;font-weight:700">✓ Email segnata come inviata il <?= htmlspecialchars(date('d/m/Y H:i',strtotime((string)$row['reply_sent_at'])),ENT_QUOTES,'UTF-8') ?></p><?php endif;?>
<form method="post" action="/idemaclima/admin/contacts/reply-sent"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><button class="btn" type="submit"><?=!empty($row['reply_sent_at'])?'Registra un nuovo invio':'Segna email come inviata'?></button></form>
<p class="muted" style="margin-bottom:0">Registra la spunta dopo aver effettivamente inviato il messaggio dal programma di posta.</p>
</div>
<form method="post" action="/idemaclima/admin/contacts/update"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<div class="formgrid"><label>Stato<select name="status"><?php foreach(['new'=>'Nuovo','in_progress'=>'In lavorazione','closed'=>'Chiuso','spam'=>'Spam'] as $k=>$v): ?><option value="<?= $k ?>" <?= $row['status']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label class="full">Note amministrative<textarea name="admin_notes" rows="5"><?= htmlspecialchars((string)($row['admin_notes']??'')) ?></textarea></label></div><p><button class="btn" type="submit">Salva</button></p></form>
</div>
<?php require __DIR__.'/_layout_end.php'; ?>
