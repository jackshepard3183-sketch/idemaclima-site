<?php require __DIR__.'/_layout_start.php'; ?>
<?php if(!empty($row['import_review_warning'])):?><div class="panel" style="margin-bottom:18px;border-color:#d97706"><strong>Da verificare</strong><p style="margin-bottom:0"><?= htmlspecialchars((string)$row['import_review_warning']) ?></p></div><?php endif;?>
<div class="toolbar"><h1>Richiesta contatto</h1><a class="btnlink" href="/idemaclima/admin/contacts">Torna all'elenco</a></div>
<div class="panel">
<p><strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></strong><br><strong>Profilo:</strong> <?= htmlspecialchars((string)($row['profile']??'')!==''?(string)$row['profile']:'Dato non disponibile') ?><br><?= htmlspecialchars($row['email']) ?><?= !empty($row['phone'])?' · '.htmlspecialchars($row['phone']):'' ?></p>
<p><?= htmlspecialchars($row['city']) ?> (<?= htmlspecialchars($row['province']) ?>) · CAP <?= htmlspecialchars((string)($row['postal_code']??'')) ?> · <?= htmlspecialchars($row['region']) ?></p>
<h3><?= htmlspecialchars($row['subject']) ?></h3><p style="white-space:pre-wrap"><?= htmlspecialchars($row['message']) ?></p>
<?php if(!empty($row['attachment_path'])): ?><p><a class="btnlink" href="/idemaclima/admin/contacts/file/<?= (int)$row['id'] ?>">Scarica allegato</a> <span class="muted"><?= htmlspecialchars((string)$row['attachment_name']) ?></span></p><?php endif; ?>
<?php if(empty($row['deleted_at'])): ?>
<form method="post" action="/idemaclima/admin/contacts/update"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<div class="formgrid"><label>Stato<select name="status"><?php foreach(['new'=>'Nuovo','in_progress'=>'In lavorazione','closed'=>'Chiuso','spam'=>'Spam'] as $k=>$v): ?><option value="<?= $k ?>" <?= $row['status']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label class="full">Note amministrative<textarea name="admin_notes" rows="5"><?= htmlspecialchars((string)($row['admin_notes']??'')) ?></textarea></label></div><p><button class="btn" type="submit">Salva</button></p></form>
<?php endif; ?>
<form method="post" action="/idemaclima/admin/contacts/<?= empty($row['deleted_at'])?'delete':'restore' ?>"<?= empty($row['deleted_at'])?' data-confirm="Spostare questa richiesta nel cestino? Potrai ripristinarla."':'' ?> style="margin-top:20px">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<?php if(!empty($row['deleted_at'])): ?><p class="muted">Questa richiesta è nel cestino.</p><?php endif; ?>
<button class="btn btn-secondary" type="submit"><?= empty($row['deleted_at'])?'Elimina':'Ripristina' ?></button>
</form>
<?php if(!empty($row['deleted_at'])): ?>
<form method="post" action="/idemaclima/admin/contacts/purge" data-confirm="Eliminare definitivamente questa richiesta e l’eventuale allegato? Questa operazione non può essere annullata." style="margin-top:12px">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="confirm_permanent" value="1">
<button class="btn btn-secondary" type="submit">Elimina definitivamente</button>
</form>
<?php endif; ?>
</div>
<?php require __DIR__.'/_layout_end.php'; ?>

