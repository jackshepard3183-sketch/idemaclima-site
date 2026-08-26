<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><div><h1>Cataloghi</h1><p class="muted">Gestisci cataloghi pubblici collegandoli preferibilmente ai PDF canonici dell'archivio documentale.</p></div><a class="btnlink" href="/admin/content/catalogs/form">Nuovo catalogo</a></div>
<table><thead><tr><th>Titolo</th><th>Anno</th><th>PDF</th><th>Stato</th><th>Ordine</th><th></th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?= htmlspecialchars($r['title'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$r['document_year'],ENT_QUOTES,'UTF-8') ?></td><td><?php if(!empty($r['document_id'])): ?>Archivio: <?= htmlspecialchars((string)$r['document_title'],ENT_QUOTES,'UTF-8') ?><?php elseif(!empty($r['pdf_path'])): ?>PDF locale<?php else: ?><span class="error">Mancante</span><?php endif; ?></td><td><span class="badge"><?= $r['published']?'Pubblicato':'Bozza' ?></span></td><td><?= (int)$r['sort_order'] ?></td><td><a href="/admin/content/catalogs/form?id=<?= (int)$r['id'] ?>">Modifica</a></td></tr><?php endforeach; ?>
</tbody></table>
<?php require __DIR__.'/_layout_end.php'; ?>
