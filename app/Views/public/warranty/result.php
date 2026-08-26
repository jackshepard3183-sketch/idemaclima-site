<section class="hero"><div class="wrap"><div class="crumbs">Home / Garanzia</div><h1><?= e($title ?? 'Garanzia') ?></h1></div></section>
<section class="content"><div class="wrap" style="max-width:760px"><div class="card">
<p><?= e($message ?? '') ?></p>
<?php if (!empty($success) && !empty($registrationId)): ?><p class="meta">Riferimento richiesta: #<?= (int)$registrationId ?></p><?php endif; ?>
<p><a class="btn" href="/garanzia">Torna alla garanzia</a></p>
</div></div></section>
