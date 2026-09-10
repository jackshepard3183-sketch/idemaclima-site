<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><div><h1>Detrazioni e Incentivi</h1><p class="muted">Gestisci testi, sezioni, domande frequenti e documenti collegati.</p></div></div>
<div class="table-wrap"><table><thead><tr><th>Pagina</th><th>Stato</th><th>Ordine</th><th>Azioni</th></tr></thead><tbody>
<?php foreach($pages as $page): ?><tr><td><strong><?= htmlspecialchars((string)$page['title'],ENT_QUOTES,'UTF-8') ?></strong><br><span class="muted"><?= htmlspecialchars((string)$page['slug'],ENT_QUOTES,'UTF-8') ?></span></td><td><span class="badge <?= !empty($page['published'])?'badge-new':'' ?>"><?= !empty($page['published'])?'Pubblicato':'Nascosto' ?></span></td><td><?= (int)$page['sort_order'] ?></td><td><a class="btnlink" href="/idemaclima/admin/editorial/form?id=<?= (int)$page['id'] ?>">Modifica</a></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>
