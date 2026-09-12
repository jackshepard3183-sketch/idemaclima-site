<?php
$title='Schede tecniche — Prodotti e modelli';
$tree=[];
$productLabel=static fn(int $count):string=>$count===1?'prodotto':'prodotti';
$modelLabel=static fn(int $count):string=>$count===1?'modello':'modelli';
foreach($products as $p){
    $group=(string)($p['category_group']?:$p['category_name']);
    $series=(string)($p['category_group']?$p['category_name']:'Prodotti');
    $tree[$group][$series][]=$p;
}
require __DIR__.'/_layout_start.php';
?>
<style>
.product-search{max-width:560px;margin:0 0 22px}.product-tree{display:grid;gap:18px}.product-group,.product-series{border:1px solid var(--line);border-radius:14px;background:#fff;overflow:hidden}.product-group>summary,.product-series>summary{cursor:pointer;list-style:none}.product-group>summary::-webkit-details-marker,.product-series>summary::-webkit-details-marker{display:none}.product-group>summary{display:flex;justify-content:space-between;gap:16px;padding:18px 20px;background:var(--navy);color:#fff;font-size:18px;font-weight:800}.product-series{margin:14px}.product-series>summary{display:flex;justify-content:space-between;gap:14px;padding:14px 16px;background:#f3f7fa;font-weight:750}.tree-count{font-size:12px;font-weight:700;opacity:.75}.product-list{display:grid}.product-row{display:grid;grid-template-columns:minmax(220px,1.4fr) minmax(110px,.55fr) 90px 110px auto;gap:16px;align-items:center;padding:14px 16px;border-top:1px solid var(--line)}.product-name strong{display:block}.product-name small{display:block;color:var(--muted);overflow-wrap:anywhere}.product-actions{display:flex;gap:11px;align-items:center}.product-actions form{margin:0}.linkbutton{border:0;padding:0;background:transparent;color:var(--blue);font-weight:650;text-decoration:underline}.product-empty{display:none;padding:26px;text-align:center;color:var(--muted);background:#fff}
@media(max-width:820px){.product-row{grid-template-columns:minmax(0,1fr) auto}.product-role,.product-models{display:none}.product-status{text-align:right}.product-actions{grid-column:1/-1}.product-group>summary{font-size:16px;padding:16px}.product-series>summary{padding:13px}}
</style>
<div class="toolbar"><div><h1>Schede tecniche</h1><p class="muted">Gestione gerarchica per categoria, serie e prodotto.</p></div><a class="btnlink" href="/idemaclima/admin/products/form">Nuovo prodotto</a></div>
<label class="product-search">Cerca prodotto, modello o serie<input type="search" id="product-filter" placeholder="Es. ISPT, Multi Split, R32"></label>
<div class="product-tree" id="product-tree">
<?php foreach($tree as $group=>$seriesList): $groupCount=array_sum(array_map('count',$seriesList)); ?>
<details class="product-group" open><summary><span><?= htmlspecialchars($group,ENT_QUOTES,'UTF-8') ?></span><span class="tree-count"><?= $groupCount ?> <?= $productLabel($groupCount) ?></span></summary>
<?php foreach($seriesList as $series=>$items): ?>
<details class="product-series" open><summary><span><?= htmlspecialchars($series,ENT_QUOTES,'UTF-8') ?></span><span class="tree-count"><?= count($items) ?></span></summary><div class="product-list">
<?php foreach($items as $p): ?><article class="product-row" data-search="<?= htmlspecialchars(strtolower($group.' '.$series.' '.$p['name'].' '.$p['slug'].' '.$p['product_role']),ENT_QUOTES,'UTF-8') ?>"><div class="product-name"><strong><?= htmlspecialchars($p['name'],ENT_QUOTES,'UTF-8') ?></strong><small><?= htmlspecialchars($p['slug'],ENT_QUOTES,'UTF-8') ?></small></div><div class="product-role"><?= htmlspecialchars($p['product_role'],ENT_QUOTES,'UTF-8') ?></div><div class="product-models"><?= (int)$p['model_count'] ?> <?= $modelLabel((int)$p['model_count']) ?></div><div class="product-status"><span class="badge"><?= htmlspecialchars(['draft'=>'Bozza','published'=>'Pubblicato','hidden'=>'Nascosto'][$p['content_status']??'']??($p['published']?'Pubblicato':'Nascosto'),ENT_QUOTES,'UTF-8') ?></span></div><div class="product-actions"><a href="/idemaclima/admin/products/form?id=<?= (int)$p['id'] ?>">Modifica</a><form method="post" action="/idemaclima/admin/products/duplicate"><input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf,ENT_QUOTES,'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="linkbutton" type="submit">Duplica</button></form></div></article><?php endforeach; ?>
</div></details><?php endforeach; ?></details><?php endforeach; ?>
</div><div class="product-empty" id="product-empty">Nessun prodotto corrisponde alla ricerca.</div>
<script nonce="<?= htmlspecialchars(\App\Core\Security::nonce(),ENT_QUOTES,'UTF-8') ?>">(()=>{const input=document.getElementById('product-filter'),tree=document.getElementById('product-tree'),empty=document.getElementById('product-empty');if(!input||!tree)return;input.addEventListener('input',()=>{const q=input.value.trim().toLowerCase();let total=0;tree.querySelectorAll('.product-series').forEach(series=>{let count=0;series.querySelectorAll('.product-row').forEach(row=>{const show=!q||row.dataset.search.includes(q);row.hidden=!show;if(show)count++});series.hidden=count===0;if(q&&count)series.open=true;total+=count});tree.querySelectorAll('.product-group').forEach(group=>{const show=[...group.querySelectorAll('.product-series')].some(series=>!series.hidden);group.hidden=!show;if(q&&show)group.open=true});empty.style.display=total?'none':'block'})})();</script>
<?php require __DIR__.'/_layout_end.php'; ?>
