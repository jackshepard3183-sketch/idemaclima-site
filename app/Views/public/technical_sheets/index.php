<section class="hero"><div class="wrap"><div class="crumbs">Home / Schede tecniche</div><h1>Schede tecniche</h1><p>Consulta prodotti, modelli, manuali e documentazione tecnica organizzati per gamma e famiglia.</p><form action="/schede-tecniche/ricerca" method="get" style="display:flex;gap:10px;max-width:720px;margin-top:24px"><input type="search" name="q" placeholder="Cerca prodotto, modello o documento" style="flex:1;padding:13px 14px;border:1px solid var(--line);border-radius:8px;font:inherit"><button class="btn" type="submit" style="border:0;cursor:pointer">Cerca</button></form></div></section>
<section class="content"><div class="wrap"><div class="grid">
<?php foreach ($categories as $category): ?>
<a class="card" href="/schede-tecniche/<?= e($category['slug']) ?>"><h2><?= e($category['name']) ?></h2><div class="meta"><?= (int)$category['product_count'] ?> prodotti · <?= (int)$category['document_count'] ?> documenti</div></a>
<?php endforeach; ?>
</div></div></section>
