<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><h1>Pagine informative</h1></div>
<table><thead><tr><th>Titolo</th><th>Slug</th><th>Stato</th><th></th></tr></thead><tbody><?php foreach($pages as $p): ?><tr><td><?= htmlspecialchars($p['title'],ENT_QUOTES,'UTF-8') ?></td><td>/<?= htmlspecialchars($p['slug'],ENT_QUOTES,'UTF-8') ?></td><td><span class="badge"><?= $p['published']?'Pubblicata':'Bozza' ?></span></td><td><a href="/admin/editorial/form?id=<?= (int)$p['id'] ?>">Modifica</a></td></tr><?php endforeach; ?></tbody></table>
<?php require __DIR__.'/_layout_end.php'; ?>
