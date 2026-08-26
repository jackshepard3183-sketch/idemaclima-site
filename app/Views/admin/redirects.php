<?php $title='Redirect SEO'; require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><div><h1>Redirect SEO</h1><p class="muted">Gestisci i vecchi URL che devono inoltrare alle nuove pagine.</p></div><a class="btnlink" href="/admin/redirects/form">Nuovo redirect</a></div>
<table><thead><tr><th>Sorgente</th><th>Destinazione</th><th>Codice</th><th>Stato</th><th>Hit</th><th>Ultimo uso</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= htmlspecialchars($r['source_path'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($r['target_path'],ENT_QUOTES,'UTF-8') ?></td><td><?= (int)$r['status_code'] ?></td><td><span class="badge"><?= $r['enabled']?'Attivo':'Disattivo' ?></span></td><td><?= (int)$r['hit_count'] ?></td><td><?= htmlspecialchars((string)($r['last_hit_at']??''),ENT_QUOTES,'UTF-8') ?></td><td><a href="/admin/redirects/form?id=<?= (int)$r['id'] ?>">Modifica</a></td></tr><?php endforeach; ?>
</tbody></table>
<?php require __DIR__.'/_layout_end.php'; ?>
