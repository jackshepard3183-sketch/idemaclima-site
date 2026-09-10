<?php $title='Dashboard IDEMA';require __DIR__.'/_layout_start.php'; ?>
<h1>Dashboard</h1><p class="page-lead">Richieste e attività principali del sito IDEMA CLIMA.</p>
<div class="cards">
<?php foreach([
 ['Contatti da gestire','contacts_new','/admin/contacts'],
 ['Garanzie da verificare','warranties_new','/admin/warranties'],
 ['Iscrizioni Campus','campus_new','/admin/campus/registrations'],
 ['Richieste EasyTool','incentives_new','/admin/incentives'],
 ['CAT da approvare','cat_pending','/admin/cat/users?status=pending'],
] as [$label,$key,$url]): ?><a class="card card-link" href="<?= $url ?>"><span class="muted"><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?></span><strong><?= (int)($counts[$key]??0) ?></strong><small>Apri la sezione →</small></a><?php endforeach; ?>
</div>
<div class="section-grid"><section class="panel"><h2>Ultime modifiche</h2><?php if($recent): ?><div class="table-wrap"><table><thead><tr><th>Attività</th><th>Contenuto</th><th>Data</th></tr></thead><tbody><?php foreach($recent as $row): ?><tr><td><?= htmlspecialchars((string)$row['action'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)$row['entity_type'],ENT_QUOTES,'UTF-8') ?> #<?= (int)$row['entity_id'] ?></td><td><?= htmlspecialchars(date('d/m/Y H:i',strtotime((string)$row['created_at'])),ENT_QUOTES,'UTF-8') ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><p class="empty">Nessuna attività recente disponibile.</p><?php endif; ?></section>
<aside class="panel"><h2>Azioni rapide</h2><div class="quick-list"><a href="/admin/products/form"><span>Nuovo prodotto</span><b>+</b></a><a href="/admin/content/catalogs/form"><span>Nuovo catalogo</span><b>+</b></a><a href="/admin/content/references/form"><span>Nuova referenza</span><b>+</b></a><a href="/admin/campus/events/form"><span>Nuovo evento Campus</span><b>+</b></a><a href="/admin/warranties"><span>Gestisci garanzie</span><b>→</b></a></div></aside></div>
<?php require __DIR__.'/_layout_end.php'; ?>
