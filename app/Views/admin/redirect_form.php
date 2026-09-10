<?php $title=((int)$redirect['id']?'Modifica':'Nuovo').' redirect'; require __DIR__.'/_layout_start.php'; ?>
<h1><?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?></h1>
<?php foreach($errors as $e): ?><div class="error"><?= htmlspecialchars($e,ENT_QUOTES,'UTF-8') ?></div><?php endforeach; ?>
<form class="panel formgrid" method="post" action="/idemaclima/admin/redirects/save"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$redirect['id'] ?>">
<label class="full">Vecchio percorso<input name="source_path" required placeholder="/vecchia-pagina/" value="<?= htmlspecialchars((string)$redirect['source_path'],ENT_QUOTES,'UTF-8') ?>"><small>Solo percorso interno, non URL completi.</small></label>
<label class="full">Nuovo percorso<input name="target_path" required placeholder="/nuova-pagina" value="<?= htmlspecialchars((string)$redirect['target_path'],ENT_QUOTES,'UTF-8') ?>"></label>
<label>Codice<select name="status_code"><?php foreach([301,302,307,308] as $c): ?><option value="<?= $c ?>" <?= (int)$redirect['status_code']===$c?'selected':'' ?>><?= $c ?></option><?php endforeach; ?></select></label>
<label class="check"><input type="checkbox" name="enabled" value="1" <?= $redirect['enabled']?'checked':'' ?>> Attivo</label>
<div class="full"><button class="btn" type="submit">Salva redirect</button> <a href="/idemaclima/admin/redirects">Annulla</a></div></form>
<?php require __DIR__.'/_layout_end.php'; ?>

