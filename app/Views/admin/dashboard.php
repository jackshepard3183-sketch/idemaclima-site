<?php $title = 'Dashboard IDEMA'; require __DIR__ . '/_layout_start.php'; ?>
<h1>Dashboard</h1><p class="muted">Benvenuto <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>.</p><div class="cards">
<?php foreach ([['Prodotti','products'],['Modelli','product_models'],['Documenti','documents'],['Garanzie','warranty_registrations'],['Eventi','events'],['Contatti','contact_submissions']] as [$label,$key]): ?><div class="card"><span class="muted"><?= $label ?></span><strong><?= (int)($counts[$key] ?? 0) ?></strong></div><?php endforeach; ?>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
