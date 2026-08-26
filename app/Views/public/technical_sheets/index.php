<section class="hero"><div class="wrap"><div class="crumbs">Home / Schede tecniche</div><h1>Schede tecniche</h1><p>Consulta prodotti, modelli, manuali e documentazione tecnica organizzati per gamma e famiglia.</p></div></section>
<section class="content"><div class="wrap"><div class="grid">
<?php foreach ($categories as $category): ?>
<a class="card" href="/schede-tecniche/<?= e($category['slug']) ?>"><h2><?= e($category['name']) ?></h2><div class="meta"><?= (int)$category['product_count'] ?> prodotti · <?= (int)$category['document_count'] ?> documenti</div></a>
<?php endforeach; ?>
</div></div></section>
