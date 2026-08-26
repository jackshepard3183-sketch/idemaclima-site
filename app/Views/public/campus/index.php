<section class="hero"><div class="wrap"><div class="crumbs">Home / Campus</div><h1>Campus IDEMA</h1><p>Eventi, incontri tecnici e appuntamenti formativi aperti al pubblico.</p></div></section>
<section class="content"><div class="wrap"><div class="grid">
<?php foreach($events as $event): ?>
<a class="card" href="/campus/<?= e($event['slug']) ?>"><h2><?= e($event['title']) ?></h2><div class="meta"><?= e(date('d/m/Y H:i',strtotime((string)$event['starts_at']))) ?><?= !empty($event['location'])?' · '.e($event['location']):'' ?></div><?php if(!empty($event['short_description'])):?><p><?= e($event['short_description']) ?></p><?php endif; ?></a>
<?php endforeach; ?>
<?php if(!$events): ?><p>Nessun evento pubblico in programma.</p><?php endif; ?>
</div><p style="margin-top:28px"><a class="btn" href="/campus/cat">Area CAT</a></p></div></section>
