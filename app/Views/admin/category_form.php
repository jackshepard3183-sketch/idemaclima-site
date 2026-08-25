<?php $title=((int)$category['id']?'Modifica':'Nuova').' categoria'; require __DIR__.'/_layout_start.php'; ?>
<h1><?= htmlspecialchars($title,ENT_QUOTES,'UTF-8') ?></h1>
<?php foreach($errors as $e): ?><div class="error"><?= htmlspecialchars($e,ENT_QUOTES,'UTF-8') ?></div><?php endforeach; ?>
<form class="panel formgrid" method="post" action="/admin/categories/save"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
<label>Nome<input name="name" required maxlength="160" value="<?= htmlspecialchars((string)$category['name'],ENT_QUOTES,'UTF-8') ?>"></label>
<label>Slug<input name="slug" maxlength="180" value="<?= htmlspecialchars((string)$category['slug'],ENT_QUOTES,'UTF-8') ?>"></label>
<label>Categoria genitore<select name="parent_id"><option value="">Nessuna</option><?php foreach($categories as $c): if((int)$c['id']===(int)$category['id']) continue; ?><option value="<?= (int)$c['id'] ?>" <?= (int)($category['parent_id']??0)===(int)$c['id']?'selected':'' ?>><?= htmlspecialchars($c['name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></label>
<label>Ordine<input type="number" name="sort_order" value="<?= (int)$category['sort_order'] ?>"></label>
<label class="check"><input type="checkbox" name="published" value="1" <?= $category['published']?'checked':'' ?>> Pubblicata</label>
<div><button class="btn" type="submit">Salva</button> <a href="/admin/categories">Annulla</a></div></form>
<?php require __DIR__.'/_layout_end.php'; ?>
