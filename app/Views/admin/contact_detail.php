<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><h1>Richiesta contatto</h1><a class="btnlink" href="/admin/contacts">Torna all'elenco</a></div>
<div class="panel">
<p><strong><?= htmlspecialchars($row['first_name'].' '.$row['last_name']) ?></strong><br><?= htmlspecialchars($row['email']) ?><?= !empty($row['phone'])?' · '.htmlspecialchars($row['phone']):'' ?></p>
<p><?= htmlspecialchars($row['city']) ?> (<?= htmlspecialchars($row['province']) ?>) · <?= htmlspecialchars($row['region']) ?></p>
<h3><?= htmlspecialchars($row['subject']) ?></h3><p style="white-space:pre-wrap"><?= htmlspecialchars($row['message']) ?></p>
<?php if(!empty($row['attachment_path'])): ?><p><a class="btnlink" href="/admin/contacts/file/<?= (int)$row['id'] ?>">Scarica allegato</a> <span class="muted"><?= htmlspecialchars((string)$row['attachment_name']) ?></span></p><?php endif; ?>
<form method="post" action="/admin/contacts/update"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<div class="formgrid"><label>Stato<select name="status"><?php foreach(['new'=>'Nuovo','in_progress'=>'In lavorazione','closed'=>'Chiuso','spam'=>'Spam'] as $k=>$v): ?><option value="<?= $k ?>" <?= $row['status']===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select></label><label class="full">Note amministrative<textarea name="admin_notes" rows="5"><?= htmlspecialchars((string)($row['admin_notes']??'')) ?></textarea></label></div><p><button class="btn" type="submit">Salva</button></p></form>
</div>
<?php require __DIR__.'/_layout_end.php'; ?>
