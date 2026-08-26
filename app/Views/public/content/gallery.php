<section class="hero"><div class="wrap"><div class="crumbs">Home / Galleria</div><h1>Galleria</h1><p>Immagini e raccolte dei progetti IDEMA.</p></div></section>
<section class="content"><div class="wrap"><div class="grid">
<?php foreach($albums as $album): ?><a class="card" href="/galleria/<?= e($album['slug']) ?>"><?php if(!empty($album['cover_image'])): ?><img class="product-img" src="<?= e($album['cover_image']) ?>" alt="<?= e($album['title']) ?>"><?php endif; ?><h2><?= e($album['title']) ?></h2><div class="meta"><?= (int)$album['image_count'] ?> immagini</div><?php if(!empty($album['description'])): ?><p><?= e($album['description']) ?></p><?php endif; ?></a><?php endforeach; ?>
</div></div></section>
