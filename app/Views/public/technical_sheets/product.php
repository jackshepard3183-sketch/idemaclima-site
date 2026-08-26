<section class="hero"><div class="wrap"><div class="crumbs"><a href="/">Home</a> / <a href="/schede-tecniche">Schede tecniche</a><?php if (!empty($product['category_slug'])): ?> / <a href="/schede-tecniche/<?= e($product['category_slug']) ?>"><?= e($product['category_name']) ?></a><?php endif; ?> / <a href="/schede-tecniche/famiglia/<?= e($product['family_slug']) ?>"><?= e($product['family_name']) ?></a> / <?= e($product['name']) ?></div><h1><?= e($product['name']) ?></h1><div><span class="status <?= e($product['status']) ?>"><?= $product['status']==='active' ? 'Disponibile' : ($product['status']==='discontinued' ? 'Fuori catalogo' : 'Non disponibile') ?></span></div></div></section>
<section class="content"><div class="wrap">
<div class="product-head">
<div><?php if (!empty($product['image_path'])): ?><img class="product-img" src="<?= e($product['image_path']) ?>" alt="<?= e($product['name']) ?>"><?php endif; ?></div>
<div>
<?php if (!empty($product['description'])): ?><p><?= e($product['description']) ?></p><?php endif; ?>
<div class="meta"><?php if (!empty($product['refrigerant'])): ?>Refrigerante: <?= e($product['refrigerant']) ?><?php endif; ?></div>
<?php if (!empty($secondaryCategories)): ?><div class="meta" style="margin-top:10px"><strong>Presente anche in:</strong> <?php foreach ($secondaryCategories as $i => $category): ?><?= $i > 0 ? ' · ' : '' ?><a href="/schede-tecniche/famiglia/<?= e($category['slug']) ?>"><?= e(($category['parent_name'] ? $category['parent_name'] . ' / ' : '') . $category['name']) ?></a><?php endforeach; ?></div><?php endif; ?>
<?php if ($models): ?><h3 style="margin-top:24px">Modelli</h3><div class="models"><?php foreach ($models as $model): ?><span class="model"><?= e($model['code']) ?></span><?php endforeach; ?></div><?php endif; ?>
</div>
</div>
<?php foreach ($documentGroups as $groupName => $documents): ?>
<section class="doc-group"><h2><?= e((string)$groupName) ?></h2><div class="doc-list">
<?php foreach ($documents as $document): ?>
<div class="doc"><div><strong><?= e($document['title']) ?></strong><?php if (!empty($document['document_year']) || !empty($document['revision'])): ?><br><small><?= !empty($document['document_year']) ? (int)$document['document_year'] : '' ?><?= !empty($document['revision']) ? ' · Rev. ' . e($document['revision']) : '' ?></small><?php endif; ?></div><a class="btn" href="/documento/<?= (int)$document['id'] ?>/download" target="_blank" rel="noopener">Apri PDF</a></div>
<?php endforeach; ?>
</div></section>
<?php endforeach; ?>
</div></section>
