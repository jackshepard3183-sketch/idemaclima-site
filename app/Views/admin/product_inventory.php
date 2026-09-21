<?php
$title='Schede tecniche — Riepilogo prodotti e documenti';
require __DIR__.'/_layout_start.php';
?>
<style>
.inventory-tools{display:grid;grid-template-columns:minmax(260px,1fr) 220px;gap:12px;margin:0 0 18px}
.inventory-table{min-width:1280px}
.inventory-table th:nth-child(1){width:64px}.inventory-table th:nth-child(2){width:170px}.inventory-table th:nth-child(3){width:170px}.inventory-table th:nth-child(4){width:200px}.inventory-table th:nth-child(5){width:140px}.inventory-table th:nth-child(6){width:210px}.inventory-table th:nth-child(7){width:220px}
.inventory-table td{vertical-align:top}.inventory-table small{display:block;color:var(--muted);margin-top:4px}.inventory-docs{white-space:pre-line}.inventory-image{max-width:210px;overflow-wrap:anywhere}.inventory-empty{display:none;padding:28px;text-align:center;color:var(--muted)}
@media(max-width:760px){.inventory-tools{grid-template-columns:1fr}}
</style>
<div class="toolbar"><div><h1>Riepilogo prodotti e documenti</h1><p class="muted">Controllo complessivo di classificazione, modelli, immagini e PDF associati.</p></div><a class="btnlink" href="/idemaclima/admin/products">Prodotti e modelli</a></div>
<div class="inventory-tools">
<label>Cerca nella tabella<input type="search" id="inventory-search" placeholder="Prodotto, modello, documento, linea…"></label>
<label>Linea<select id="inventory-line"><option value="">Tutte le linee</option><?php foreach(array_values(array_unique(array_map(static fn($p)=>(string)$p['product_line'],$products))) as $line):?><option><?=htmlspecialchars($line,ENT_QUOTES,'UTF-8')?></option><?php endforeach;?></select></label>
</div>
<div class="table-wrap"><table class="admin-data-table inventory-table"><thead><tr><th>ID</th><th>Linea</th><th>Tipologia</th><th>Prodotto</th><th>Ruolo</th><th>Modelli</th><th>Immagine associata</th><th>Documenti associati</th><th>Stato</th><th></th></tr></thead><tbody id="inventory-body">
<?php foreach($products as $p): $search=strtolower(implode(' ',array_map('strval',$p)));?><tr data-line="<?=htmlspecialchars((string)$p['product_line'],ENT_QUOTES,'UTF-8')?>" data-search="<?=htmlspecialchars($search,ENT_QUOTES,'UTF-8')?>"><td><?= (int)$p['id'] ?></td><td><?=htmlspecialchars((string)$p['product_line'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars((string)$p['product_type'],ENT_QUOTES,'UTF-8')?></td><td><strong><?=htmlspecialchars((string)$p['name'],ENT_QUOTES,'UTF-8')?></strong></td><td><?=htmlspecialchars((string)$p['product_role'],ENT_QUOTES,'UTF-8')?></td><td><?=htmlspecialchars((string)($p['model_codes']?:'—'),ENT_QUOTES,'UTF-8')?></td><td class="inventory-image"><?=htmlspecialchars((string)($p['image_path']?:'—'),ENT_QUOTES,'UTF-8')?></td><td class="inventory-docs"><?=htmlspecialchars((string)($p['document_list']?:'—'),ENT_QUOTES,'UTF-8')?></td><td><span class="badge"><?=htmlspecialchars((string)$p['content_status'],ENT_QUOTES,'UTF-8')?></span></td><td class="col-action"><a href="/idemaclima/admin/products/form?id=<?= (int)$p['id'] ?>">Modifica</a></td></tr><?php endforeach;?>
</tbody></table></div><div class="inventory-empty" id="inventory-empty">Nessun prodotto corrisponde ai filtri.</div>
<script nonce="<?=htmlspecialchars(\App\Core\Security::nonce(),ENT_QUOTES,'UTF-8')?>">(()=>{const search=document.getElementById('inventory-search'),line=document.getElementById('inventory-line'),rows=[...document.querySelectorAll('#inventory-body tr')],empty=document.getElementById('inventory-empty');const apply=()=>{const q=search.value.trim().toLowerCase(),l=line.value;let visible=0;rows.forEach(row=>{const show=(!q||row.dataset.search.includes(q))&&(!l||row.dataset.line===l);row.hidden=!show;if(show)visible++});empty.style.display=visible?'none':'block'};search.addEventListener('input',apply);line.addEventListener('change',apply)})();</script>
<?php require __DIR__.'/_layout_end.php'; ?>
