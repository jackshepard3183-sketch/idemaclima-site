<section class="hero"><div class="wrap"><div class="crumbs"><a href="/">Home</a> / <a href="/schede-tecniche">Schede tecniche</a> / <a href="/schede-tecniche/<?= e($family['parent_slug']) ?>"><?= e($family['parent_name']) ?></a> / <?= e($family['name']) ?></div><h1><?= e($family['name']) ?></h1><p>Prodotti disponibili e archivio storico della famiglia.</p></div></section>
<section class="content"><div class="wrap"><div class="grid">
<?php foreach ($products as $product): ?>
<a class="card" href="/schede-tecniche/prodotto/<?= e($product['slug']) ?>">
<?php if (!empty($product['image_path'])): ?><img src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>" style="width:100%;aspect-ratio:16/10;object-fit:contain;margin-bottom:14px"><?php endif; ?>
<h2><?= e($product['name']) ?></h2>
<div class="meta"><?= (int)$product['model_count'] ?> modelli · <?= (int)$product['document_count'] ?> documenti</div>
<div style="margin-top:12px"><span class="status <?= e($product['status']) ?>"><?= $product['status']==='active' ? 'Disponibile' : ($product['status']==='discontinued' ? 'Fuori catalogo' : 'Non disponibile') ?></span></div>
</a>
<?php endforeach; ?>
</div></div></section>
