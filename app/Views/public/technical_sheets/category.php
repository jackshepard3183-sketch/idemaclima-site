<section class="hero"><div class="wrap"><div class="crumbs"><a href="/">Home</a> / <a href="/schede-tecniche">Schede tecniche</a> / <?= e($category['name']) ?></div><h1><?= e($category['name']) ?></h1><p>Seleziona una famiglia di prodotto.</p></div></section>
<section class="content"><div class="wrap"><div class="grid">
<?php foreach ($families as $family): ?>
<a class="card" href="/schede-tecniche/famiglia/<?= e($family['slug']) ?>"><h2><?= e($family['name']) ?></h2><div class="meta"><?= (int)$family['product_count'] ?> prodotti</div></a>
<?php endforeach; ?>
</div></div></section>
