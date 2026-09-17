<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><div><h1>Importazione garanzie WPForms</h1><p class="muted">Importazione protetta delle pratiche storiche e copia degli allegati nello storage privato.</p></div><a class="btnlink" href="/idemaclima/admin/warranties">Torna alle registrazioni</a></div>
<div class="panel">
<p><strong>Il file deve contenere esclusivamente pratiche già verificate.</strong> Gli ID WPForms già presenti vengono ignorati. Gli avvisi di verifica vengono conservati nella pratica.</p>
<form method="post" action="/idemaclima/admin/warranties/import/run" enctype="multipart/form-data">
<input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
<label>Manifest JSON<input type="file" name="manifest" accept="application/json,.json" required></label>
<button class="btn" type="submit">Importa pratiche</button>
</form>
</div>
<?php require __DIR__.'/_layout_end.php'; ?>
