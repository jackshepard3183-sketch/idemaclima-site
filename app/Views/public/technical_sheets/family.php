<?php
$special=['ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-R32 COLOR'];
$isMono=$family['parent_slug']==='linea-residenziale-r32'&&$family['name']==='Mono Split';
$isMulti=$family['parent_slug']==='linea-residenziale-r32'&&$family['name']==='Multi Split';
$productGroups=[''=>$products];
if($isMulti){
    $groupOrder=['Unità esterne','Unità interne a parete','Unità interne a cassetta','Unità interne canalizzate','Unità interne console','Unità interne soffitto/pavimento','Accessori','Altri prodotti'];
    $productGroups=[];
    foreach($products as $product){
        $name=strtoupper(trim((string)$product['name']));
        $role=(string)($product['product_role']??'');
        $label=match(true){
            $role==='accessory'=>'Accessori',
            (preg_match('/^[2-5]M/', $name)===1||str_starts_with($name,'MWTF'))=>'Unità esterne',
            preg_match('/^(IS|WT)/', $name)===1=>'Unità interne a parete',
            str_starts_with($name,'IQ')=>'Unità interne a cassetta',
            str_starts_with($name,'IF')=>'Unità interne canalizzate',
            str_starts_with($name,'IU')=>'Unità interne console',
            str_starts_with($name,'IT')=>'Unità interne soffitto/pavimento',
            default=>'Altri prodotti',
        };
        $productGroups[$label][]=$product;
    }
    $ordered=[];
    foreach($groupOrder as $label)if(isset($productGroups[$label]))$ordered[$label]=$productGroups[$label];
    $productGroups=$ordered+$productGroups;
} elseif(!$isMono) {
    $roleLabels=[
        'outdoor_unit'=>'Unità esterne',
        'indoor_unit'=>'Unità interne',
        'complete_system'=>'Sistemi completi',
        'accessory'=>'Accessori',
        'controller'=>'Comandi e controlli',
        'tank'=>'Serbatoi',
        'other'=>'Altri prodotti',
    ];
    $roleGroups=[];
    foreach($products as $product){
        $role=(string)($product['product_role']??'other');
        $label=$roleLabels[$role]??$roleLabels['other'];
        $roleGroups[$label][]=$product;
    }
    if(count($roleGroups)>1){
        $ordered=[];
        foreach($roleLabels as $label)if(isset($roleGroups[$label]))$ordered[$label]=$roleGroups[$label];
        $productGroups=$ordered+$roleGroups;
    }
}
?>
<style>
.ts-hero{padding:58px 0 66px;background:var(--gradient-cool);border-bottom:1px solid var(--border)}.ts-crumbs{margin:0 0 28px;color:var(--muted-foreground);font-size:13px}.ts-eyebrow{color:var(--primary-deep);font-size:12px;font-weight:700;letter-spacing:.2em;text-transform:uppercase}.ts-hero h1{margin:8px 0;font-size:clamp(42px,6vw,68px);line-height:1.04}.ts-subtitle{max-width:820px;color:var(--muted-foreground);font-size:18px;line-height:1.65}.ts-content{padding:48px 0 88px}.ts-back{display:inline-flex;margin-bottom:34px;color:var(--muted-foreground);font-size:14px}.products-list{display:grid;gap:30px}.product-section,.product-section-list{display:grid;gap:14px}.product-section-head{display:flex;align-items:center;gap:14px;padding:0 2px 12px;border-bottom:1px solid var(--border)}.product-section-icon{display:grid;width:44px;height:44px;place-items:center;flex:0 0 44px;border-radius:14px;background:rgba(145,208,37,.12);color:var(--primary-deep)}.product-section-icon svg{width:23px;height:23px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}.product-section-title h2{margin:0;font-size:24px}.product-section-title span{display:block;margin-top:3px;color:var(--muted-foreground);font-size:12px}.product-item{scroll-margin-top:110px;overflow:hidden;border:1px solid rgba(145,208,37,.55);border-radius:16px;background:#fff}.product-item[open]{border-color:var(--primary);box-shadow:var(--shadow-elegant)}.product-card{display:grid;grid-template-columns:64px 1fr auto;gap:16px;align-items:center;padding:16px 20px;cursor:pointer;list-style:none}.product-card::-webkit-details-marker{display:none}.product-visual{display:grid;box-sizing:border-box;width:64px;height:48px;place-items:center;padding:4px;overflow:visible;border-radius:12px;background:var(--gradient-cool)}.product-visual img{display:block;width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain;object-position:center}.product-title-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.product-info h2{margin:0;font-size:20px}.product-status{padding:3px 9px;border-radius:999px;background:#ff5c62;color:#fff;font-size:10px;font-weight:700}.product-meta{display:flex;gap:9px;margin-top:7px;color:var(--muted-foreground);font-size:12px}.product-open svg{width:17px;fill:none;stroke:var(--primary-deep);stroke-width:2;transition:.3s}.product-item[open] .product-open svg{transform:rotate(180deg)}.product-panel{padding:6px 20px 22px}.product-page-link{display:inline-flex;padding:11px 16px;border-radius:12px;background:var(--primary);color:#10261f;font-size:13px;font-weight:700}.inline-description{max-width:900px;color:var(--muted-foreground);line-height:1.65}.inline-section{margin-top:18px}.inline-section h3{margin:0 0 10px;font-size:15px}.chip-list{display:flex;gap:8px;flex-wrap:wrap}.chip{padding:7px 10px;border:1px solid var(--border);border-radius:999px;background:var(--muted);font-size:12px}.spec-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.spec{display:flex;justify-content:space-between;gap:15px;padding:9px 12px;border:1px solid var(--border);border-radius:10px;font-size:12px}.spec span{color:var(--muted-foreground)}.document-groups{display:grid;gap:8px;margin-top:18px}.doc-group-head{display:flex;align-items:center;gap:8px;margin-bottom:5px}.doc-group-head h3{margin:0;padding:5px 10px;border-radius:999px;background:rgba(145,208,37,.1);color:var(--primary-deep);font-size:10px}.doc-count{color:var(--muted-foreground);font-size:10px}.doc-list{display:grid;grid-template-columns:repeat(2,1fr);gap:5px}.doc{display:flex;justify-content:space-between;gap:12px;padding:8px 12px;border:1px solid var(--border);border-radius:11px}.doc strong{font-size:13px}.doc-open{color:var(--primary-deep)}.empty-docs{margin-top:16px;padding:14px;border-radius:12px;background:var(--muted);color:var(--muted-foreground);font-size:13px}@media(max-width:700px){.ts-hero{padding:44px 0 48px}.product-card{grid-template-columns:54px 1fr auto;padding:14px}.product-visual{width:54px}.product-meta span:last-child{display:none}.product-panel{padding:4px 14px 18px}.spec-grid,.doc-list{grid-template-columns:1fr}}
</style>
<section class="ts-hero"><div class="wrap"><div class="ts-crumbs"><a href="/idemaclima/schede-tecniche">Schede tecniche</a> / <a href="/idemaclima/schede-tecniche/<?=e($family['parent_slug'])?>"><?=e($family['parent_name'])?></a> / <?=e($family['name'])?></div><p class="ts-eyebrow"><?=e($family['parent_name'])?></p><h1><?=e($family['name'])?></h1><p class="ts-subtitle">Consulta prodotti, modelli e documentazione tecnica ufficiale disponibile per questa linea.</p></div></section>
<section class="ts-content"><div class="wrap"><a class="ts-back" href="/idemaclima/schede-tecniche/<?=e($family['parent_slug'])?>">← Tutte le linee di prodotto</a><div class="products-list">
<?php foreach($productGroups as $productGroupLabel=>$productGroup):?><section class="product-section"><?php if($productGroupLabel!==''):?><header class="product-section-head"><span class="product-section-icon" aria-hidden="true"><?php if($productGroupLabel==='Unità esterne'):?><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h10M7 13h6M18 16h.01"/></svg><?php elseif(str_starts_with($productGroupLabel,'Unità interne')):?><svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="9" rx="2"/><path d="M7 11h10M8 18c1-1 2-1 3 0M14 18c1-1 2-1 3 0"/></svg><?php else:?><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z"/><path d="M8 11h8M8 14h5"/></svg><?php endif;?></span><span class="product-section-title"><h2><?=e($productGroupLabel)?></h2><span><?=count($productGroup)?> <?=count($productGroup)===1?'serie disponibile':'serie disponibili'?></span></span></header><?php endif;?><div class="product-section-list">
<?php foreach($productGroup as $p):$dedicated=$isMono&&in_array($p['name'],$special,true);$d=$productDetails[(int)$p['id']]??['models'=>[],'documentGroups'=>[]];$docTotal=array_sum(array_map('count',$d['documentGroups']));?>
<?php if($dedicated):?><a class="product-item dedicated-product" id="prodotto-<?=e($p['slug'])?>" href="/idemaclima/schede-tecniche/prodotto/<?=e($p['slug'])?>"><div class="product-card"><div class="product-visual"><?php if(!empty($p['image_path'])):?><img src="<?=e($p['image_path'])?>" alt="<?=e($p['name'])?>" loading="lazy"><?php endif;?></div><div class="product-info"><div class="product-title-row"><h2><?=e($p['name'])?></h2><?php if($p['status']!=='active'):?><span class="product-status">ⓘ FUORI CATALOGO</span><?php endif;?></div><div class="product-meta"><span>▧ <?=$docTotal?> <?=$docTotal===1?'scheda':'schede'?></span><span>· PAGINA PRODOTTO</span></div></div><span class="dedicated-open">Apri →</span></div></a>
<?php else:?><details class="product-item" id="prodotto-<?=e($p['slug'])?>"><summary class="product-card"><div class="product-visual"><?php if(!empty($p['image_path'])):?><img src="<?=e($p['image_path'])?>" alt="<?=e($p['name'])?>" loading="lazy"><?php endif;?></div><div class="product-info"><div class="product-title-row"><h2><?=e($p['name'])?></h2><?php if($p['status']!=='active'):?><span class="product-status">ⓘ FUORI CATALOGO</span><?php endif;?></div><div class="product-meta"><span>▧ <?=$docTotal?> <?=$docTotal===1?'scheda':'schede'?></span><span>· SCHEDE TECNICHE · TABELLE RESE · DETRAZIONI FISCALI · CONTO TERMICO · MANUALI</span></div></div><span class="product-open"><svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></span></summary><div class="product-panel">
<?php if($d['models']):?><section class="inline-section models-section"><h3>Modelli</h3><div class="chip-list"><?php foreach($d['models'] as $x):?><span class="chip"><?=e($x['code'])?></span><?php endforeach;?></div></section><?php endif;?>
<?php if($d['documentGroups']):?><div class="document-groups"><?php foreach($d['documentGroups'] as $group=>$docs):?><section class="doc-group group-<?=e(strtolower(str_replace(' ','-',$group)))?>"><div class="doc-group-head"><h3><?=e(strtoupper($group))?></h3><span class="doc-count"><?=count($docs)?> file</span></div><div class="doc-list"><?php foreach($docs as $x):?><a class="doc" href="/idemaclima/documento/<?=(int)$x['id']?>/download" target="_blank" rel="noopener"><strong><?=e($x['title'])?></strong><span class="doc-open">↗</span></a><?php endforeach;?></div></section><?php endforeach;?></div><?php else:?><div class="empty-docs">Documentazione tecnica non ancora disponibile.</div><?php endif;?>
</div></details><?php endif;?><?php endforeach;?></div></section><?php endforeach;?></div></div></section>
<script nonce="<?=e(\App\Core\Security::nonce())?>">if(location.hash){const x=document.querySelector(location.hash);if(x&&x.tagName==='DETAILS')x.open=true}</script>
<style>.doc-group-head h3{border:1px solid rgba(145,208,37,.25)}.doc-group.group-detrazioni-fiscali h3,.doc-group.group-conto-termico h3{border-color:#a9e4ea;background:#d7f5f7;color:#1d6970}.doc-group.group-manuali h3{border-color:#dce9e2;background:#edf5f1;color:#52645d}</style>
<style>.products-list{gap:9px}.product-card{padding:11px 16px;gap:12px}.product-panel{padding:0 16px 14px}.inline-section{margin-top:11px}.inline-section h3{margin-bottom:6px}.chip-list{gap:5px}.chip{padding:5px 8px;line-height:1.2}.models-section .chip-list{overflow-x:auto;flex-wrap:nowrap;padding:1px 0 4px;scrollbar-width:thin}.models-section .chip{flex:0 0 auto;white-space:nowrap;word-break:keep-all}.document-groups{gap:4px;margin-top:11px}.doc-group-head{margin-bottom:3px}.doc-group-head h3{padding:4px 8px}.doc-list{gap:4px}.doc{padding:6px 10px;line-height:1.25}.spec-grid{gap:5px}.spec{padding:7px 9px}@media(max-width:700px){.product-card{padding:10px 12px;gap:10px}.product-panel{padding:0 12px 12px}.product-info h2{font-size:18px}.models-section .chip-list{margin-right:-4px}.doc{min-height:32px}}</style>
<style>.dedicated-product{display:block;color:inherit;text-decoration:none}.dedicated-product:hover{border-color:var(--primary);box-shadow:var(--shadow-elegant)}.dedicated-open{color:var(--primary-deep);font-size:13px;font-weight:700;white-space:nowrap}.product-info h2,.chip{overflow-wrap:normal;word-break:keep-all}.doc-group.group-schede-tecniche h3,.doc-group.group-tabelle-rese h3{border-color:rgba(145,208,37,.3);background:rgba(145,208,37,.1);color:var(--primary-deep)}@media(max-width:700px){.dedicated-open{font-size:0}.dedicated-open:after{content:'→';font-size:18px}}</style>
