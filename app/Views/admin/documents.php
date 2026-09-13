<?php $title='Documenti'; require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><div><h1>Documenti</h1><p class="muted">Archivio PDF e documentazione tecnica.</p></div><a class="btnlink" href="/idemaclima/admin/documents/form">Nuovo documento</a></div>
<form class="panel formgrid" method="get" action="/idemaclima/admin/documents" style="margin-bottom:18px">
<label>Cerca<input type="search" name="q" value="<?= htmlspecialchars($q,ENT_QUOTES,'UTF-8') ?>" placeholder="Titolo, nome file o revisione"></label>
<label>Tipologia<select name="type"><option value="">Tutte le tipologie</option><?php foreach($types as $t): ?><option value="<?= (int)$t['id'] ?>" <?= $typeId===(int)$t['id']?'selected':'' ?>><?= htmlspecialchars($t['name'],ENT_QUOTES,'UTF-8') ?></option><?php endforeach; ?></select></label>
<label>Stato<select name="status"><option value="">Tutti</option><option value="published" <?= $status==='published'?'selected':'' ?>>Pubblicati</option><option value="hidden" <?= $status==='hidden'?'selected':'' ?>>Non pubblicati</option></select></label>
<label>Ordine<select name="sort"><option value="order" <?= $sort==='order'?'selected':'' ?>>Ordine editoriale</option><option value="title" <?= $sort==='title'?'selected':'' ?>>Titolo</option><option value="type" <?= $sort==='type'?'selected':'' ?>>Tipologia</option><option value="filename" <?= $sort==='filename'?'selected':'' ?>>Nome file</option><option value="year" <?= $sort==='year'?'selected':'' ?>>Anno</option></select></label>
<label>Direzione<select name="dir"><option value="asc" <?= $dir==='ASC'?'selected':'' ?>>A–Z / crescente</option><option value="desc" <?= $dir==='DESC'?'selected':'' ?>>Z–A / decrescente</option></select></label>
<div style="align-self:end"><button class="btn" type="submit">Applica</button> <a href="/idemaclima/admin/documents">Azzera filtri</a></div>
</form>
<p class="muted"><?= count($documents) ?> documenti trovati</p>
<?php $sortLink=static function(string $key,string $label)use($q,$typeId,$status,$sort,$dir):string{$next=$sort===$key&&$dir==='ASC'?'desc':'asc';$arrow=$sort===$key?($dir==='ASC'?' ↑':' ↓'):'';$url='?'.http_build_query(['q'=>$q,'type'=>$typeId?:'','status'=>$status,'sort'=>$key,'dir'=>$next]);return '<a href="'.$url.'" style="color:inherit;text-decoration:none">'.htmlspecialchars($label,ENT_QUOTES,'UTF-8').$arrow.'</a>';}; ?>
<div style="overflow:auto"><table><thead><tr><th><?= $sortLink('title','Titolo') ?></th><th><?= $sortLink('type','Tipo') ?></th><th><?= $sortLink('filename','File') ?></th><th><?= $sortLink('year','Anno') ?></th><th><?= $sortLink('revision','Revisione') ?></th><th><?= $sortLink('status','Pubblicato') ?></th><th></th></tr></thead><tbody>
<?php foreach($documents as $d): ?><tr><td><?= htmlspecialchars($d['title'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars($d['type_name'],ENT_QUOTES,'UTF-8') ?></td><td class="muted"><?= htmlspecialchars($d['filename'],ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)($d['document_year']??''),ENT_QUOTES,'UTF-8') ?></td><td><?= htmlspecialchars((string)($d['revision']??''),ENT_QUOTES,'UTF-8') ?></td><td><?= $d['published']?'Sì':'No' ?></td><td><a href="/idemaclima/admin/documents/form?id=<?= (int)$d['id'] ?>">Modifica</a></td></tr><?php endforeach; ?>
<?php if(!$documents): ?><tr><td colspan="7" class="muted">Nessun documento corrisponde ai filtri selezionati.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>

<?php /* deploy-sync 2026-09-13 */ ?>
