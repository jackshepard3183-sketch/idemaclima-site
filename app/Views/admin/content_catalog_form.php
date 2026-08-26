<?php require __DIR__.'/_layout_start.php'; ?>
<h1><?= (int)$row['id']?'Modifica catalogo':'Nuovo catalogo' ?></h1>
<?php foreach($errors as $er): ?><div class="error"><?= htmlspecialchars($er,ENT_QUOTES,'UTF-8') ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" action="/admin/content/catalogs/save" class="panel">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
<div class="formgrid">
<label>Titolo<input name="title" maxlength="220" value="<?= htmlspecialchars((string)$row['title'],ENT_QUOTES,'UTF-8') ?>" required></label>
<label>Slug<input name="slug" value="<?= htmlspecialchars((string)$row['slug'],ENT_QUOTES,'UTF-8') ?>"></label>
<label class="full">Descrizione<textarea name="description" rows="5" maxlength="5000"><?= htmlspecialchars((string)$row['description'],ENT_QUOTES,'UTF-8') ?></textarea></label>
<label>Anno<input type="number" min="1990" max="<?= (int)date('Y')+1 ?>" name="document_year" value="<?= htmlspecialchars((string)$row['document_year'],ENT_QUOTES,'UTF-8') ?>"></label>
<label>Ordine<input type="number" name="sort_order" value="<?= (int)$row['sort_order'] ?>"></label>
<label>Copertina<input type="file" name="cover_image" accept="image/jpeg,image/png,image/webp"><?php if(!empty($row['cover_image'])): ?><small>Attuale: <?= htmlspecialchars((string)$row['cover_image'],ENT_QUOTES,'UTF-8') ?></small><?php endif; ?></label>
<label class="full">Documento canonico<select name="document_id"><option value="">Nessun documento selezionato</option><?php foreach($documents as $d): ?><option value="<?= (int)$d['id'] ?>" <?= (string)($row['document_id']??'')===(string)$d['id']?'selected':'' ?>><?= htmlspecialchars($d['title'].' — '.$d['filename'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select><small>Preferibile per i cataloghi già migrati nell'archivio documentale: usa lo stesso PDF, lo stesso download tracciato e nessun doppione fisico.</small></label>
<label class="full">Oppure carica un PDF<input type="file" name="pdf_file" accept="application/pdf"><?php if(!empty($row['pdf_path'])): ?><small>PDF locale attuale: <?= htmlspecialchars((string)$row['pdf_path'],ENT_QUOTES,'UTF-8') ?></small><?php endif; ?><small>Non caricare un PDF se hai selezionato un documento canonico.</small></label>
<label class="check"><input type="checkbox" name="published" value="1" <?= !empty($row['published'])?'checked':'' ?>> Pubblicato</label>
</div><p><button class="btn" type="submit">Salva</button></p></form>
<?php require __DIR__.'/_layout_end.php'; ?>
