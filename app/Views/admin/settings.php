<?php require __DIR__.'/_layout_start.php';$fields=\App\Controllers\Admin\SettingsController::fields($group); ?>
<div class="toolbar"><div><h1><?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?></h1><p class="muted">Modifica solo i dati utilizzati dal sito e dalle notifiche.</p></div></div>
<?php if($saved): ?><p class="panel" style="border-color:#9acb70;background:#f3faed">Impostazioni salvate correttamente.</p><?php endif; ?>
<form class="panel" method="post" action="/idemaclima/admin/settings/<?= htmlspecialchars($group,ENT_QUOTES,'UTF-8') ?>/save"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><div class="formgrid">
<?php foreach($fields as $key=>$meta):$value=(string)($settings[$key]??''); ?>
<?php if($meta['type']==='bool'): ?><label class="check full"><input type="checkbox" name="<?= $key ?>" value="1" <?= $value==='1'?'checked':'' ?>> <?= htmlspecialchars($meta['label'],ENT_QUOTES,'UTF-8') ?></label>
<?php elseif($meta['type']==='textarea'): ?><label class="full"><?= htmlspecialchars($meta['label'],ENT_QUOTES,'UTF-8') ?><textarea name="<?= $key ?>"><?= htmlspecialchars($value,ENT_QUOTES,'UTF-8') ?></textarea></label>
<?php else: ?><label><?= htmlspecialchars($meta['label'],ENT_QUOTES,'UTF-8') ?><input type="<?= $meta['type']==='email'?'email':($meta['type']==='url'?'url':'text') ?>" name="<?= $key ?>" value="<?= htmlspecialchars($value,ENT_QUOTES,'UTF-8') ?>"></label><?php endif; ?>
<?php endforeach; ?></div><p><button class="btn" type="submit">Salva impostazioni</button></p></form><?php require __DIR__.'/_layout_end.php'; ?>
