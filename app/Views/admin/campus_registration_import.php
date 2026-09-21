<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar"><div><h1>Importazione iscrizioni Campus</h1><p class="muted">Controllo e importazione dell’archivio WordPress senza notifiche.</p></div><a class="btnlink" href="/idemaclima/admin/campus/registrations">Torna alle iscrizioni</a></div>
<div class="panel">
  <p>Il manifest deve contenere esattamente <strong>132 registrazioni</strong>. I due eventi R290 vengono accorpati; i due eventi eliminati vengono creati come archivio interno con data non disponibile. I possibili duplicati restano presenti e segnalati.</p>
  <form method="post" enctype="multipart/form-data" action="/idemaclima/admin/campus/registrations/import/run">
    <input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>">
    <label>Manifest JSON<input type="file" name="manifest" accept="application/json,.json" required></label>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:16px"><button class="btn" name="mode" value="check" type="submit">Esegui controllo</button><button class="btn btn-secondary" name="mode" value="execute" type="submit">Importa 132 registrazioni</button></div>
  </form>
</div>
<?php require __DIR__.'/_layout_end.php'; ?>
