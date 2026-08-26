<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><h1>Contatti</h1></div>
<table><thead><tr><th>Data</th><th>Nome</th><th>Email</th><th>Oggetto</th><th>Stato</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= htmlspecialchars((string)$r['created_at']) ?></td><td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td><td><?= htmlspecialchars($r['email']) ?></td><td><?= htmlspecialchars($r['subject']) ?></td><td><span class="badge"><?= htmlspecialchars($r['status']) ?></span></td><td><a href="/admin/contacts/view?id=<?= (int)$r['id'] ?>">Apri</a></td></tr><?php endforeach; ?>
</tbody></table>
<?php require __DIR__.'/_layout_end.php'; ?>
