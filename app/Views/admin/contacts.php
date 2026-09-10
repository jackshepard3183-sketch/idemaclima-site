<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><div><h1>Contatti</h1><p class="muted">Richieste inviate dal modulo pubblico.</p></div></div>
<form method="get" class="panel" style="margin-bottom:18px"><div class="inlineform"><label>Stato<select name="status"><option value="">Tutti</option><?php foreach(['new'=>'Nuova','in_progress'=>'In lavorazione','closed'=>'Chiusa','spam'=>'Spam'] as $value=>$label):?><option value="<?=$value?>" <?=($status??'')===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label><button class="btn" type="submit">Filtra</button></div></form>
<div class="table-wrap"><table><thead><tr><th>Data</th><th>Nome</th><th>Email</th><th>Oggetto</th><th>Stato</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= htmlspecialchars((string)$r['created_at']) ?></td><td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td><td><?= htmlspecialchars($r['email']) ?></td><td><?= htmlspecialchars($r['subject']) ?></td><td><span class="badge"><?= htmlspecialchars($r['status']) ?></span></td><td><a href="/idemaclima/admin/contacts/view?id=<?= (int)$r['id'] ?>">Apri</a></td></tr><?php endforeach; ?><?php if(!$rows):?><tr><td colspan="6" class="empty">Nessuna richiesta.</td></tr><?php endif;?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>
