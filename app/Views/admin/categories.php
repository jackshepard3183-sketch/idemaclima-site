<?php $title='Categorie'; require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><div><h1>Categorie</h1><p class="muted">Gerarchia gamme/famiglie/sottocategorie.</p></div><a class="btnlink" href="/admin/categories/form">Nuova categoria</a></div>
<div style="overflow:auto"><table><thead><tr><th>Nome</th><th>Genitore</th><th>Slug</th><th>Ordine</th><th>Pubblicata</th><th></th></tr></thead><tbody>
<?php foreach($categories as $c): ?><tr><td><strong><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></strong></td><td><?= htmlspecialchars((string)($c['parent_name']??''),ENT_QUOTES,'UTF-8') ?></td><td class="muted"><?= htmlspecialchars($c['slug'],ENT_QUOTES,'UTF-8') ?></td><td><?= (int)$c['sort_order'] ?></td><td><?= $c['published']?'Sì':'No' ?></td><td><a href="/admin/categories/form?id=<?= (int)$c['id'] ?>">Modifica</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>
