<section class="hero"><div class="wrap"><div class="crumbs">Home / Cataloghi</div><h1>Cataloghi</h1><p>Consulta e scarica i cataloghi IDEMA disponibili.</p></div></section>
<section class="content"><div class="wrap"><div class="grid">
<?php foreach($catalogs as $catalog): ?>
<article class="card"><?php if(!empty($catalog['cover_image'])): ?><img class="product-img" src="<?= e($catalog['cover_image']) ?>" alt="<?= e($catalog['title']) ?>"><?php endif; ?><h2><?= e($catalog['title']) ?></h2><?php if(!empty($catalog['document_year'])): ?><div class="meta"><?= (int)$catalog['document_year'] ?></div><?php endif; ?><?php if(!empty($catalog['description'])): ?><p><?= nl2br(e($catalog['description'])) ?></p><?php endif; ?><a class="btn" href="<?= e($catalog['pdf_path']) ?>" target="_blank" rel="noopener">Scarica PDF</a></article>
<?php endforeach; ?>
</div></div></section>
