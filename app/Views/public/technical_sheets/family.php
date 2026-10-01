<?php
$special=['ISPT-R32','ISAX-R32','ISZZ-R32','WTZ-R32','WTMC-R32','WTMC-BLK-R32'];
$isMono=$family['parent_slug']==='linea-residenziale-r32'&&$family['name']==='Mono Split';
$isMulti=$family['parent_slug']==='linea-residenziale-r32'&&$family['name']==='Multi Split';
$isVrf=$family['parent_slug']==='linea-vrf';
$isIdronica=$family['parent_slug']==='linea-idronica';
$isOtherProducts=$family['parent_slug']==='altri-prodotti';
$useMultiLayout=$isMulti||$isVrf||$isIdronica||$isOtherProducts||($family['parent_slug']==='linea-residenziale-r32'&&in_array($family['name'],['Multi Pro','Accessori'],true))||$family['parent_slug']==='linea-commerciale-r32';
$isDistribution=$family['parent_slug']==='distribuzione-aria'&&$family['name']==='Sistemi e componenti';
$normalizeVrfDescription=static function(string $text):string{
    $text=html_entity_decode($text,ENT_QUOTES|ENT_HTML5,'UTF-8');
    $text=str_replace([chr(13),chr(10),chr(9)],' ',$text);
    $text=(string)preg_replace('/\s+/u',' ',trim($text));
    $text=(string)preg_replace('/\s+([,.;:!?])/u','$1',$text);
    $text=(string)preg_replace('/([,;:])(?=\S)/u','$1 ',$text);
    $text=(string)preg_replace('/\bwi[ -]?fi\b/iu','Wi-Fi',$text);
    $text=(string)preg_replace('/\bdc inverter\b/iu','DC Inverter',$text);
    $text=(string)preg_replace('/\bvrf\b/iu','VRF',$text);
    $text=(string)preg_replace('/\br410a\b/iu','R410A',$text);
    $text=(string)preg_replace('/\br32\b/iu','R32',$text);
    $text=(string)preg_replace('/\bper sistema VRF\b/iu','per sistemi VRF',$text);
    $text=(string)preg_replace('/\bunità interna cassette\b/iu','unità interna a cassetta',$text);
    $text=(string)preg_replace('/\bunità interna canalizzato\b/iu','unità interna canalizzata',$text);
    if($text!==''&&!preg_match('/[.!?]$/u',$text))$text.='.';
    if($text!=='')$text=mb_strtoupper(mb_substr($text,0,1,'UTF-8'),'UTF-8').mb_substr($text,1,null,'UTF-8');
    return $text;
};
$productGroups=[''=>$products];
if($isMono){
    $productGroups=['Serie attuali'=>[],'Serie fuori catalogo'=>[]];
    foreach($products as $product){
        $label=in_array($product['name'],$special,true)?'Serie attuali':'Serie fuori catalogo';
        $productGroups[$label][]=$product;
    }
    $productGroups=array_filter($productGroups);
} elseif($isMulti||($family['parent_slug']==='linea-commerciale-r32'&&$family['name']==='Unità interne')){
    $groupOrder=['Unità esterne','Unità interne a parete','Unità interne a cassetta','Unità interne canalizzabili','Unità interne console a pavimento','Unità interne soffitto/pavimento','Unità interne a colonna','Accessori','Altri prodotti'];
    $productGroups=[];
    foreach($products as $product){
        $name=strtoupper(trim((string)$product['name']));
        $role=(string)($product['product_role']??'');
        $label=match(true){
            $role==='accessory'=>'Accessori',
            preg_match('/^[2-5]M/', $name)===1=>'Unità esterne',
            (str_starts_with($name,'MWTF')||preg_match('/^(IS|WT)/', $name)===1)=>'Unità interne a parete',
            (str_starts_with($name,'IQ')||str_starts_with($name,'IC'))=>'Unità interne a cassetta',
            str_starts_with($name,'IFG')=>'Unità interne a colonna',
            str_starts_with($name,'IF')=>'Unità interne console a pavimento',
            str_starts_with($name,'IU')=>'Unità interne soffitto/pavimento',
            (str_starts_with($name,'IT')||str_starts_with($name,'IMI2'))=>'Unità interne canalizzabili',
            default=>'Altri prodotti',
        };
        $productGroups[$label][]=$product;
    }
    $ordered=[];
    foreach($groupOrder as $label)if(isset($productGroups[$label]))$ordered[$label]=$productGroups[$label];
    $productGroups=$ordered+$productGroups;
} elseif($isVrf&&$family['name']==='Unità interne'){
    $groupOrder=['Unità interne a parete','Unità interne a cassetta','Unità interne canalizzabili','Unità interne console a pavimento','Unità interne soffitto/pavimento','Altre unità interne'];
    $productGroups=[];
    foreach($products as $product){
        $description=mb_strtolower((string)($product['description']??''),'UTF-8');
        $label=match(true){
            str_contains($description,'parete')=>'Unità interne a parete',
            str_contains($description,'cassetta')=>'Unità interne a cassetta',
            str_contains($description,'canalizz')=>'Unità interne canalizzabili',
            str_contains($description,'console')=>'Unità interne console a pavimento',
            str_contains($description,'soffitto/pavimento')=>'Unità interne soffitto/pavimento',
            default=>'Altre unità interne',
        };
        $productGroups[$label][]=$product;
    }
    $ordered=[];
    foreach($groupOrder as $label)if(isset($productGroups[$label]))$ordered[$label]=$productGroups[$label];
    $productGroups=$ordered+$productGroups;
} elseif(!$isMono) {
    $roleLabels=[
        'outdoor_unit'=>'Unità esterne',
        'indoor_unit'=>(($family['slug']??'')==='linea-idronica--linea-idronica-terminali-idronici' ? 'Unità fancoil' : 'Unità interne'),
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
.ts-hero{padding:58px 0 66px;background:var(--gradient-cool);border-bottom:1px solid var(--border)}.ts-crumbs{margin:0 0 28px;color:var(--muted-foreground);font-size:13px}.ts-eyebrow{color:var(--primary-deep);font-size:12px;font-weight:700;letter-spacing:.2em;text-transform:uppercase}.ts-hero h1{margin:8px 0;font-size:clamp(42px,6vw,68px);line-height:1.04}.ts-subtitle{max-width:820px;color:var(--muted-foreground);font-size:18px;line-height:1.65}.ts-content{padding:48px 0 88px}.ts-back{display:inline-flex;margin-bottom:34px;color:var(--muted-foreground);font-size:14px}.products-list{display:grid;gap:30px}.product-section,.product-section-list{display:grid;gap:14px}.product-section-head{display:flex;align-items:center;gap:14px;padding:0 2px 12px;border-bottom:1px solid var(--border)}.product-section-icon{display:grid;width:44px;height:44px;place-items:center;flex:0 0 44px;border-radius:14px;background:rgba(145,208,37,.12);color:var(--primary-deep)}.product-section-icon svg{width:23px;height:23px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}.product-section-title h2{margin:0;font-size:24px}.product-section-title span{display:block;margin-top:3px;color:var(--muted-foreground);font-size:12px}.product-item{scroll-margin-top:110px;overflow:hidden;border:1px solid rgba(145,208,37,.55);border-radius:16px;background:#fff}.product-item[open]{border-color:var(--primary);box-shadow:var(--shadow-elegant)}.product-card{display:grid;grid-template-columns:64px 1fr auto;gap:16px;align-items:center;padding:16px 20px;cursor:pointer;list-style:none}.product-card::-webkit-details-marker{display:none}.product-visual{display:grid;box-sizing:border-box;width:64px;height:64px;place-items:center;padding:4px;overflow:visible;border-radius:12px;background:var(--gradient-cool)}.product-visual img{display:block;width:auto;height:auto;max-width:100%;max-height:100%;object-fit:contain;object-position:center}.product-title-row{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.product-info h2{margin:0;font-size:20px}.product-status{padding:3px 9px;border-radius:999px;background:#ff5c62;color:#fff;font-size:10px;font-weight:700}.product-meta{display:flex;gap:9px;margin-top:7px;color:var(--muted-foreground);font-size:12px}.product-open svg{width:17px;fill:none;stroke:var(--primary-deep);stroke-width:2;transition:.3s}.product-item[open] .product-open svg{transform:rotate(180deg)}.product-panel{padding:6px 20px 22px}.product-page-link{display:inline-flex;padding:11px 16px;border-radius:12px;background:var(--primary);color:#10261f;font-size:13px;font-weight:700}.inline-description{max-width:900px;color:var(--muted-foreground);line-height:1.65}.inline-section{margin-top:18px}.inline-section h3{margin:0 0 10px;font-size:15px}.chip-list{display:flex;gap:8px;flex-wrap:wrap}.chip{padding:7px 10px;border:1px solid var(--border);border-radius:999px;background:var(--muted);font-size:12px}.spec-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.spec{display:flex;justify-content:space-between;gap:15px;padding:9px 12px;border:1px solid var(--border);border-radius:10px;font-size:12px}.spec span{color:var(--muted-foreground)}.document-groups{display:grid;gap:8px;margin-top:18px}.doc-group-head{display:flex;align-items:center;gap:8px;margin-bottom:5px}.doc-group-head h3{margin:0;padding:5px 10px;border-radius:999px;background:rgba(145,208,37,.1);color:var(--primary-deep);font-size:10px}.doc-count{color:var(--muted-foreground);font-size:10px}.doc-list{display:grid;grid-template-columns:repeat(2,1fr);gap:5px}.doc{display:flex;justify-content:space-between;gap:12px;padding:8px 12px;border:1px solid var(--border);border-radius:11px}.doc strong{font-size:13px}.doc-open{color:var(--primary-deep)}.empty-docs{margin-top:16px;padding:14px;border-radius:12px;background:var(--muted);color:var(--muted-foreground);font-size:13px}@media(max-width:700px){.ts-hero{padding:44px 0 48px}.product-card{grid-template-columns:54px 1fr auto;padding:14px}.product-visual{width:54px;height:54px}.product-meta span:last-child{display:none}.product-panel{padding:4px 14px 18px}.spec-grid,.doc-list{grid-template-columns:1fr}}
</style>
<style>
.distribution-catalog-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:22px}.distribution-catalog-card{display:flex;min-width:0;flex-direction:column;overflow:hidden;border:1px solid var(--border);border-radius:20px;background:#fff;box-shadow:var(--shadow-soft);transition:.35s var(--ease)}.distribution-catalog-card:hover{transform:translateY(-4px);border-color:var(--primary);box-shadow:var(--shadow-elegant)}.distribution-catalog-cover{display:grid;min-height:336px;place-items:center;padding:18px;background:var(--gradient-cool);overflow:hidden}.distribution-catalog-cover img{display:block;width:212px;height:300px;max-width:100%;object-fit:contain;filter:drop-shadow(0 9px 10px rgba(19,48,36,.16));transition:.35s var(--ease)}.distribution-catalog-cover img.pdf-symbol{width:92px;height:92px;filter:none}.distribution-catalog-card:hover .distribution-catalog-cover img:not(.pdf-symbol){transform:scale(1.025)}.distribution-catalog-info{display:flex;flex:1;flex-direction:column;padding:20px}.distribution-catalog-info p{margin:0 0 6px;color:var(--primary-deep);font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase}.distribution-catalog-info h2{margin:0 0 18px;font-size:18px;line-height:1.3}.distribution-catalog-download{display:inline-flex;height:42px;align-items:center;justify-content:center;gap:8px;margin-top:auto;border-radius:999px;background:var(--muted);font-size:14px;font-weight:700;transition:.3s}.distribution-catalog-download svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2}.distribution-catalog-card:hover .distribution-catalog-download{background:var(--gradient-primary);color:var(--primary-ink)}@media(max-width:1120px){.distribution-catalog-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:850px){.distribution-catalog-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:560px){.distribution-catalog-grid{grid-template-columns:1fr}}
</style>
<section class="ts-hero"><div class="wrap"><div class="ts-crumbs"><a href="/idemaclima/schede-tecniche">Schede tecniche</a> / <a href="/idemaclima/schede-tecniche/<?=e($family['parent_slug'])?>"><?=e($family['parent_name'])?></a> / <?=e($family['name'])?></div><p class="ts-eyebrow">— <?=e($family['parent_name'])?></p><h1><?=e($family['name'])?></h1><p class="ts-subtitle">Consulta prodotti, modelli e documentazione tecnica ufficiale disponibile per questa linea.</p></div></section>
<section class="ts-content"><div class="wrap"><a class="ts-back" href="/idemaclima/schede-tecniche/<?=e($family['parent_slug'])?>">← Tutte le linee di prodotto</a>
<?php if($isDistribution):?><div class="distribution-catalog-grid"><?php foreach($products as $p):$d=$productDetails[(int)$p['id']]??['documentGroups'=>[]];$firstDoc=null;foreach($d['documentGroups'] as $docs){if($docs){$firstDoc=$docs[0];break;}}$pdfSymbol=in_array($p['slug'],['distribuzione-aria--componenti','distribuzione-aria--modulo-plenum'],true);?><a class="distribution-catalog-card" href="<?=$firstDoc?'/idemaclima/documento/'.(int)$firstDoc['id'].'/download':'#'?>" target="_blank" rel="noopener"><div class="distribution-catalog-cover"><?php if(!empty($p['image_path'])):?><img class="<?=$pdfSymbol?'pdf-symbol':''?>" src="<?=e($p['image_path'])?>" alt="<?=e($p['name'])?>" loading="lazy"><?php endif;?></div><div class="distribution-catalog-info"><p>Zonificazione</p><h2><?=e($p['name'])?></h2><span class="distribution-catalog-download"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path><path d="M12 15V3"></path></svg>Scarica PDF</span></div></a><?php endforeach;?></div>
<?php else:?><div class="products-list">
<?php foreach($productGroups as $productGroupLabel=>$productGroup):?><section class="product-section<?=$isMono?' mono-product-section':($useMultiLayout?' multi-product-section':'')?>"><?php if($productGroupLabel!==''):?><header class="product-section-head"><span class="product-section-icon" aria-hidden="true"><?php if($productGroupLabel==='Unità esterne'):?><svg viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h10M7 13h6M18 16h.01"/></svg><?php elseif(str_starts_with($productGroupLabel,'Unità interne')):?><svg viewBox="0 0 24 24"><rect x="3" y="6" width="18" height="9" rx="2"/><path d="M7 11h10M8 18c1-1 2-1 3 0M14 18c1-1 2-1 3 0"/></svg><?php else:?><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z"/><path d="M8 11h8M8 14h5"/></svg><?php endif;?></span><span class="product-section-title"><h2><?=e($productGroupLabel)?></h2><?php if(!$isMono):?><span><?=count($productGroup)?> <?=count($productGroup)===1?'serie disponibile':'serie disponibili'?></span><?php endif;?></span></header><?php endif;?><div class="product-section-list<?=$isMono?' mono-product-grid':($useMultiLayout?' multi-product-grid':'')?>">
<?php foreach($productGroup as $p):$dedicated=$isMono&&in_array($p['name'],$special,true);$d=$productDetails[(int)$p['id']]??['models'=>[],'documentGroups'=>[]];$docTotal=array_sum(array_map('count',$d['documentGroups']));$customModelBadges=array_values(array_filter(array_map('trim',preg_split('/\R/u',(string)($p['badges_text']??''))?:[])));$displayModels=$customModelBadges?array_map(static fn($code)=>['code'=>$code],$customModelBadges):$d['models'];if(!$customModelBadges&&$isMulti&&preg_match('/^(IS|WT|MWTF)/i',(string)$p['name']))$displayModels=array_values(array_filter($displayModels,static fn($model)=>str_contains(strtoupper((string)($model['code']??'')),'UI-')));if(!$displayModels)$displayModels=[['code'=>(string)$p['name']]];$displayImagePath=(string)($p['image_path']??'');$displayDescription=(string)($p['description']??'');if($isMulti&&preg_match('/^(IS|WT|MWTF)/i',(string)$p['name']))$displayDescription=(string)preg_replace('/^Sistema Mono Split DC Inverter in pompa di calore serie ([^ ]+)/i','Unità interna a parete serie $1 per sistemi Multi Split DC Inverter in pompa di calore',$displayDescription);if($isVrf)$displayDescription=$normalizeVrfDescription($displayDescription);?>
<?php if($dedicated):?><a class="product-item dedicated-product" id="prodotto-<?=e($p['slug'])?>" href="/idemaclima/schede-tecniche/prodotto/<?=e($p['slug'])?>"><div class="product-card"><div class="product-visual"><?php if($displayImagePath!==''):?><img src="<?=e($displayImagePath)?>" alt="<?=e($p['name'])?>" loading="lazy"><?php endif;?></div><div class="product-info"><div class="product-title-row"><h2><?=e($p['name'])?></h2><?php if($p['status']!=='active'):?><span class="product-status">ⓘ FUORI CATALOGO</span><?php endif;?></div><?php if($isMono):?><?php if($displayDescription!==''):?><p class="mono-description"><?=e($displayDescription)?></p><?php endif;?><?php if($displayModels):?><div class="mono-models"><?php foreach($displayModels as $x):?><span<?php if(($x['status']??'active')==='discontinued'):?> class="model-discontinued"<?php endif;?>><?=e($x['code'])?><?php if(($x['status']??'active')==='discontinued'):?><small>FUORI CATALOGO</small><?php endif;?></span><?php endforeach;?></div><?php endif;?><?php else:?><div class="product-meta"><span>▧ <?=$docTotal?> <?=$docTotal===1?'scheda':'schede'?></span><span>· PAGINA PRODOTTO</span></div><?php endif;?></div><span class="dedicated-open"><?=$isMono?'Scopri la serie →':'Apri →'?></span></div></a>
<?php else:?><details class="product-item" id="prodotto-<?=e($p['slug'])?>"><summary class="product-card"><div class="product-visual"><?php if($displayImagePath!==''):?><img src="<?=e($displayImagePath)?>" alt="<?=e($p['name'])?>" loading="lazy"><?php endif;?></div><div class="product-info"><div class="product-title-row"><h2><?=e($p['name'])?></h2><?php if($p['status']!=='active'):?><span class="product-status">ⓘ FUORI CATALOGO</span><?php endif;?></div><?php if(($isMono||$useMultiLayout)&&$displayDescription!==''):?><p class="mono-description"><?=e($displayDescription)?></p><?php endif;?><?php if(($isMono||$useMultiLayout)&&$displayModels):?><div class="multi-models"><?php foreach($displayModels as $x):?><span<?php if(($x['status']??'active')==='discontinued'):?> class="model-discontinued"<?php endif;?>><?=e($x['code'])?><?php if(($x['status']??'active')==='discontinued'):?><small>FUORI CATALOGO</small><?php endif;?></span><?php endforeach;?></div><?php endif;?><div class="product-meta"><?php if($isMono||$useMultiLayout):?><span>Documentazione tecnica</span><?php else:?><span>▧ <?=$docTotal?> <?=$docTotal===1?'scheda':'schede'?></span><span>· SCHEDE TECNICHE · TABELLE RESE · DETRAZIONI FISCALI · CONTO TERMICO · MANUALI</span><?php endif;?></div></div><span class="product-open"><svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg></span></summary><div class="product-panel">
<?php if(!$isMono&&!$useMultiLayout&&$d['models']):?><section class="inline-section models-section"><?php if(!$isMono):?><h3>Modelli</h3><?php endif;?><div class="chip-list"><?php foreach($d['models'] as $x):?><span class="chip<?php if(($x['status']??'active')==='discontinued'):?> model-discontinued<?php endif;?>"><?=e($x['code'])?><?php if(($x['status']??'active')==='discontinued'):?><small>FUORI CATALOGO</small><?php endif;?></span><?php endforeach;?></div></section><?php endif;?>
<?php if($d['documentGroups']):?><div class="document-groups"><?php foreach($d['documentGroups'] as $group=>$docs):?><section class="doc-group group-<?=e(strtolower(str_replace(' ','-',$group)))?>"><div class="doc-group-head"><h3><?=e(strtoupper($group))?></h3><?php if(!$isMono):?><span class="doc-count"><?=count($docs)?> file</span><?php endif;?></div><div class="doc-list"><?php foreach($docs as $x):?><a class="doc" href="/idemaclima/documento/<?=(int)$x['id']?>/download" target="_blank" rel="noopener"><strong><?=e($x['title'])?></strong><span class="doc-open">↗</span></a><?php endforeach;?></div></section><?php endforeach;?></div><?php else:?><div class="empty-docs">Documentazione tecnica non ancora disponibile.</div><?php endif;?>
</div></details><?php endif;?><?php endforeach;?></div></section><?php endforeach;?></div><?php endif;?></div></section>
<script nonce="<?=e(\App\Core\Security::nonce())?>">if(location.hash){const x=document.querySelector(location.hash);if(x&&x.tagName==='DETAILS')x.open=true}</script>
<style>.doc-group-head h3{border:1px solid rgba(145,208,37,.25)}.doc-group.group-detrazioni-fiscali h3,.doc-group.group-conto-termico h3{border-color:#a9e4ea;background:#d7f5f7;color:#1d6970}.doc-group.group-manuali h3{border-color:#dce9e2;background:#edf5f1;color:#52645d}</style>
<style>.products-list{gap:9px}.product-card{padding:11px 16px;gap:12px}.product-panel{padding:0 16px 14px}.inline-section{margin-top:11px}.inline-section h3{margin-bottom:6px}.chip-list{gap:5px}.chip{padding:5px 8px;line-height:1.2}.models-section .chip-list{overflow-x:auto;flex-wrap:nowrap;padding:1px 0 4px;scrollbar-width:thin}.models-section .chip{flex:0 0 auto;white-space:nowrap;word-break:keep-all}.document-groups{gap:4px;margin-top:11px}.doc-group-head{margin-bottom:3px}.doc-group-head h3{padding:4px 8px}.doc-list{gap:4px}.doc{padding:6px 10px;line-height:1.25}.spec-grid{gap:5px}.spec{padding:7px 9px}@media(max-width:700px){.product-card{padding:10px 12px;gap:10px}.product-panel{padding:0 12px 12px}.product-info h2{font-size:18px}.models-section .chip-list{margin-right:-4px}.doc{min-height:32px}}</style>
<style>.dedicated-product{display:block;color:inherit;text-decoration:none}.dedicated-product:hover{border-color:var(--primary);box-shadow:var(--shadow-elegant)}.dedicated-open{color:var(--primary-deep);font-size:13px;font-weight:700;white-space:nowrap}.product-info h2,.chip{overflow-wrap:normal;word-break:keep-all}.doc-group.group-schede-tecniche h3,.doc-group.group-tabelle-rese h3{border-color:rgba(145,208,37,.3);background:rgba(145,208,37,.1);color:var(--primary-deep)}@media(max-width:700px){.dedicated-open{font-size:0}.dedicated-open:after{content:'→';font-size:18px}}</style>
<?php if($isMono):?><style>
.mono-product-grid{grid-template-columns:repeat(3,minmax(0,1fr));gap:22px;align-items:start}
.mono-product-section{gap:20px}.mono-product-section+.mono-product-section{margin-top:34px}
.mono-product-section .product-section-head{padding:0 2px 14px;border-bottom-color:rgba(145,208,37,.45)}
.mono-product-section .product-section-icon{background:var(--gradient-primary);color:var(--primary-ink)}
.mono-product-section .product-section-title h2{font-size:27px}
.mono-product-grid .product-item{height:auto;border-color:var(--border);border-radius:18px;box-shadow:var(--shadow-soft)}
.mono-product-grid .product-item{position:relative}.mono-product-grid .product-item:before{content:'';position:absolute;z-index:2;top:0;left:20px;right:20px;height:3px;border-radius:0 0 999px 999px;background:var(--primary)}
.mono-product-grid .product-item:hover,.mono-product-grid .product-item[open]{border-color:rgba(145,208,37,.65);box-shadow:var(--shadow-elegant)}
.mono-product-grid .product-card{display:flex;min-height:100%;flex-direction:column;align-items:stretch;gap:0;padding:20px}
.mono-product-grid .product-visual{position:relative;width:100%;height:220px;padding:12px;overflow:hidden;border-radius:14px;background:var(--gradient-cool)}
.mono-product-grid .product-visual img{position:absolute;inset:12px;width:calc(100% - 24px);height:calc(100% - 24px);max-width:none;max-height:none;object-fit:contain;transition:transform .35s var(--ease)}
.mono-product-grid .product-item:hover .product-visual img{transform:scale(1.035)}
.mono-product-grid .product-info{width:100%;padding:18px 2px 14px}
.mono-product-grid .product-title-row{justify-content:space-between;align-items:flex-start}
.mono-product-grid .product-info h2{font-size:24px;letter-spacing:-.02em}
.mono-description{display:-webkit-box;min-height:66px;margin:12px 0 14px;overflow:hidden;color:var(--muted-foreground);font-size:13px;line-height:1.65;-webkit-box-orient:vertical;-webkit-line-clamp:3}.mono-models{display:flex;gap:6px;flex-wrap:wrap;margin-top:4px}.mono-models span{padding:5px 8px;border:1px solid var(--border);border-radius:999px;background:var(--muted);font-size:11px}.mono-description-panel{display:none;min-height:0}.mono-product-grid details .models-section{margin-top:2px;padding:0 2px 10px}.mono-product-grid details .models-section .chip-list{flex-wrap:wrap;overflow:visible}.mono-product-grid details .models-section .chip{font-size:11px}
.mono-product-grid .product-meta{margin-top:8px;color:var(--muted-foreground);font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.mono-product-grid .dedicated-open{display:flex;align-items:center;justify-content:space-between;width:100%;padding:13px 15px;border:1px solid rgba(145,208,37,.25);border-radius:12px;background:rgba(145,208,37,.10);transition:.3s}
.mono-product-grid .dedicated-product:hover .dedicated-open{background:var(--gradient-primary);color:var(--primary-ink)}
.mono-product-grid details .product-card{display:grid;grid-template-columns:1fr auto;min-height:0;align-items:center;padding:20px}
.mono-product-grid details .product-visual{grid-column:1/-1;width:100%;height:250px;padding:12px}
.mono-product-grid details .product-info{padding:18px 2px 2px}
.mono-product-grid details .product-open{align-self:end;margin:0 5px 7px 12px}
.mono-product-grid .product-status{position:absolute;z-index:4;top:34px;right:34px;box-shadow:0 5px 14px rgba(78,18,20,.16)}.multi-product-grid .product-status{position:absolute;z-index:4;top:34px;right:34px;box-shadow:0 5px 14px rgba(78,18,20,.16)}
.mono-product-grid details .product-meta{margin-top:13px;padding:12px 14px;border:1px solid var(--border);border-radius:12px;background:var(--muted);color:var(--foreground);font-size:13px;letter-spacing:0;text-transform:none}
.mono-product-grid details[open] .product-meta{border-color:rgba(145,208,37,.45);background:rgba(145,208,37,.10)}
.mono-product-grid .product-panel{padding:0 14px 16px}
.mono-product-grid .document-groups{margin-top:4px}
@media(max-width:980px){.mono-product-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}}
@media(max-width:640px){.mono-product-grid{grid-template-columns:1fr;gap:14px}.mono-product-grid .product-visual,.mono-product-grid details .product-visual{height:220px}.mono-product-grid details .product-card{grid-template-columns:1fr auto}.mono-product-grid .dedicated-open{font-size:13px}.mono-product-grid .dedicated-open:after{content:none}}
</style><?php endif;?>

<?php if($isMono):?><style>
.mono-product-grid details .product-meta{display:none}
.mono-product-grid details .product-open{display:flex;grid-column:1/-1;width:auto;align-items:center;justify-content:space-between;align-self:auto;margin:0 2px 2px;padding:13px 15px;border:1px solid rgba(145,208,37,.35);border-radius:12px;background:rgba(145,208,37,.12);color:var(--primary-deep);font-size:13px;font-weight:700;transition:.3s}
.mono-product-grid details .product-open:before{content:'Documentazione tecnica'}
.mono-product-grid details[open] .product-open{background:var(--gradient-primary);color:var(--primary-ink)}
.mono-product-grid details .product-open svg{flex:0 0 17px}
</style><?php endif;?>

<?php if($useMultiLayout):?><style>
.multi-product-section{gap:20px}.multi-product-section+.multi-product-section{margin-top:38px}.multi-product-section .product-section-head{padding:0 2px 14px;border-bottom-color:rgba(145,208,37,.45)}.multi-product-section .product-section-icon{background:var(--gradient-primary);color:var(--primary-ink)}.multi-product-section .product-section-title h2{font-size:27px}.multi-product-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:22px;align-items:start}.multi-product-grid .product-item{position:relative;height:auto;border-color:var(--border);border-radius:18px;box-shadow:var(--shadow-soft)}.multi-product-grid .product-item:before{content:'';position:absolute;z-index:2;top:0;left:20px;right:20px;height:3px;border-radius:0 0 999px 999px;background:var(--primary)}.multi-product-grid .product-item:hover,.multi-product-grid .product-item[open]{border-color:rgba(145,208,37,.65);box-shadow:var(--shadow-elegant)}.multi-product-grid .product-card{display:grid;grid-template-columns:1fr auto;align-items:center;gap:0;padding:20px}.multi-product-grid .product-visual{position:relative;grid-column:1/-1;width:100%;height:220px;padding:14px;overflow:hidden;border-radius:14px;background:var(--gradient-cool)}.multi-product-grid .product-visual img{position:absolute;inset:14px;width:calc(100% - 28px);height:calc(100% - 28px);max-width:none;max-height:none;object-fit:contain;transition:transform .35s var(--ease)}.multi-product-grid .product-item:hover .product-visual img{transform:scale(1.035)}.multi-product-grid .product-info{width:100%;padding:18px 2px 8px}.multi-product-grid .product-title-row{justify-content:space-between;align-items:flex-start}.multi-product-grid .product-info h2{font-size:23px;letter-spacing:-.02em}.multi-product-grid .mono-description{display:-webkit-box;min-height:64px;margin:11px 0 13px;overflow:hidden;color:var(--muted-foreground);font-size:13px;line-height:1.65;-webkit-box-orient:vertical;-webkit-line-clamp:3}.multi-models{display:flex;gap:6px;flex-wrap:wrap;margin:3px 0 10px}.multi-models span{padding:5px 8px;border:1px solid var(--border);border-radius:999px;background:var(--muted);font-size:11px}.multi-product-grid .product-meta{display:none}.multi-product-grid .product-open{display:flex;grid-column:1/-1;width:auto;align-items:center;justify-content:space-between;margin:5px 2px 2px;padding:13px 15px;border:1px solid rgba(145,208,37,.35);border-radius:12px;background:rgba(145,208,37,.12);color:var(--primary-deep);font-size:13px;font-weight:700;transition:.3s}.multi-product-grid .product-open:before{content:'Documentazione tecnica'}.multi-product-grid details[open] .product-open{background:var(--gradient-primary);color:var(--primary-ink)}.multi-product-grid .product-open svg{flex:0 0 17px}.multi-product-grid .product-panel{padding:0 16px 18px}.multi-product-grid .models-section{margin-top:2px}.multi-product-grid .models-section h3{font-size:14px}.multi-product-grid .models-section .chip-list{flex-wrap:wrap;overflow:visible}.multi-product-grid .document-groups{margin-top:12px}.multi-product-grid .doc-count{display:none}@media(max-width:980px){.multi-product-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}}@media(max-width:640px){.multi-product-grid{grid-template-columns:1fr;gap:14px}.multi-product-grid .product-visual{height:210px}.multi-product-section .product-section-title h2{font-size:23px}}
</style><?php endif;?>
<?php if ($isMono || $useMultiLayout): ?>
<style id="idema-visual-image-normalizer">
.mono-product-grid .product-visual img,.multi-product-grid .product-visual img{transform:translate(var(--img-x,0px),var(--img-y,0px)) scale(var(--img-scale,1));transform-origin:center center;}
.mono-product-grid .product-item:hover .product-visual img,.multi-product-grid .product-item:hover .product-visual img,.multi-product-grid .multi-product-card:hover .product-visual img{transform:translate(var(--img-x,0px),var(--img-y,0px)) scale(var(--img-scale,1)) scale(1.035);}
</style>
<script nonce="<?= e(\App\Core\Security::nonce()) ?>">
(function(){
  var selector=".mono-product-grid .product-visual img,.multi-product-grid .product-visual img";
  function normalize(img){
    if(!img.naturalWidth||!img.naturalHeight)return;
    try{
      var src=new URL(img.currentSrc||img.src,location.href);
      if(src.origin!==location.origin)return;
      var maxSide=420, ratio=Math.min(1,maxSide/Math.max(img.naturalWidth,img.naturalHeight));
      var w=Math.max(1,Math.round(img.naturalWidth*ratio)), h=Math.max(1,Math.round(img.naturalHeight*ratio));
      var canvas=document.createElement("canvas"); canvas.width=w; canvas.height=h;
      var ctx=canvas.getContext("2d",{willReadFrequently:true}); ctx.drawImage(img,0,0,w,h);
      var data=ctx.getImageData(0,0,w,h).data, minX=w, minY=h, maxX=-1, maxY=-1;
      for(var y=0;y<h;y++){for(var x=0;x<w;x++){if(data[(y*w+x)*4+3]>12){if(x<minX)minX=x;if(x>maxX)maxX=x;if(y<minY)minY=y;if(y>maxY)maxY=y;}}}
      if(maxX<minX||maxY<minY)return;
      var box=img.parentElement.getBoundingClientRect();
      var inset=img.closest(".multi-product-grid")?14:12;
      var availW=Math.max(1,box.width-inset*2), availH=Math.max(1,box.height-inset*2);
      var naturalW=img.naturalWidth, naturalH=img.naturalHeight;
      var base=Math.min(availW/naturalW,availH/naturalH);
      var bw=(maxX-minX+1)/ratio, bh=(maxY-minY+1)/ratio;
      var wide=bw/bh>1.55;
      var targetW=availW*(wide?.82:.76), targetH=availH*(wide?.58:.76);
      var wanted=Math.min(targetW/(bw*base),targetH/(bh*base));
      var qualityCap=Math.max(1,Math.min(2.35,1/base));
      var scale=Math.max(1,Math.min(wanted,qualityCap));
      var cx=((minX+maxX+1)/2)/ratio, cy=((minY+maxY+1)/2)/ratio;
      var offsetX=(cx-naturalW/2)*base*scale, offsetY=(cy-naturalH/2)*base*scale;
      img.style.setProperty("--img-scale",scale.toFixed(3));
      img.style.setProperty("--img-x",(-offsetX).toFixed(1)+"px");
      img.style.setProperty("--img-y",(-offsetY).toFixed(1)+"px");
      img.dataset.visualNormalized="1";
    }catch(e){}
  }
  function run(){document.querySelectorAll(selector).forEach(function(img){if(img.complete)normalize(img);else img.addEventListener("load",function(){normalize(img);},{once:true});});}
  if(document.readyState==="loading")document.addEventListener("DOMContentLoaded",run);else run();
  window.addEventListener("resize",function(){clearTimeout(window.__idemaImageTimer);window.__idemaImageTimer=setTimeout(run,120);});
})();
</script>
<?php endif; ?>
<?php /* idema-visual-image-normalizer */ ?>


<style id="idema-product-card-uniformity">
.multi-product-grid .product-status{position:absolute!important;z-index:4;top:34px!important;right:34px!important;box-shadow:0 5px 14px rgba(78,18,20,.16)}
.mono-product-grid .product-visual,.mono-product-grid details .product-visual,.multi-product-grid .product-visual{height:220px!important}
.mono-product-grid .mono-description,.multi-product-grid .mono-description{display:block!important;min-height:0!important;overflow:visible!important;-webkit-line-clamp:unset!important}
.mono-models,.multi-models{display:flex!important;gap:6px;flex-wrap:wrap;margin:4px 0 10px}
.mono-models span,.multi-models span{display:inline-flex;align-items:center;padding:5px 8px;border:1px solid var(--border);border-radius:999px;background:var(--muted);font-size:11px;line-height:1.25;white-space:nowrap}
.mono-product-grid .models-section,.multi-product-grid .models-section{display:none!important}
.mono-product-grid .doc-list,.multi-product-grid .doc-list{grid-template-columns:1fr!important}
.mono-product-grid .doc,.multi-product-grid .doc{min-width:0;overflow-x:auto}
.mono-product-grid .doc strong,.multi-product-grid .doc strong{white-space:nowrap;word-break:keep-all}
@media(max-width:640px){.mono-product-grid .product-visual,.mono-product-grid details .product-visual,.multi-product-grid .product-visual{height:210px!important}}
</style>
<?php /* idema-product-card-uniformity */ ?>

<?php /* idemaclima-family-layout-v2 */ ?>
<style id="idema-description-wrap">
.mono-description{white-space:normal;text-wrap:pretty;overflow-wrap:normal;word-break:normal;hyphens:none;line-height:1.6}
.multi-product-grid .product-info h2,.mono-product-grid .product-info h2{text-wrap:balance;overflow-wrap:normal;word-break:normal}
.multi-models span,.mono-models span{white-space:nowrap;word-break:keep-all}
</style>
<?php /* idema-description-wrap */ ?>


<style id="idema-document-link-nowrap">
.mono-product-grid .doc,.multi-product-grid .doc{min-width:0;overflow:hidden}
.mono-product-grid .doc strong,.multi-product-grid .doc strong{display:block;min-width:0;flex:1 1 auto;white-space:nowrap;word-break:normal;overflow:visible;text-overflow:clip}
.mono-product-grid .doc-open,.multi-product-grid .doc-open{flex:0 0 auto}
</style>
<script id="idema-document-link-fit">
(function(){
  function fitDocumentTitles(){
    document.querySelectorAll('.mono-product-grid .doc strong,.multi-product-grid .doc strong').forEach(function(el){
      var size=13;
      el.style.fontSize=size+'px';
      while(el.scrollWidth>el.clientWidth&&size>6.5){
        size-=0.25;
        el.style.fontSize=size+'px';
      }
    });
  }
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',fitDocumentTitles);
  }else{
    fitDocumentTitles();
  }
  var timer;
  window.addEventListener('resize',function(){
    clearTimeout(timer);
    timer=setTimeout(fitDocumentTitles,120);
  });
  document.addEventListener('toggle',function(event){
    if(event.target.matches&&event.target.matches('details[open]')) fitDocumentTitles();
  },true);
})();
</script>
<?php /* idema-document-link-nowrap */ ?>
<style id="idema-vrf-layout-normalization">
/* LINEA VRF: layout Multi Split, testi completi e collegamenti sempre su una riga. */
.multi-product-grid .mono-description{display:block!important;min-height:0!important;overflow:visible!important;white-space:normal!important;text-wrap:pretty;overflow-wrap:normal;word-break:normal;hyphens:none;-webkit-line-clamp:unset!important}
.multi-product-grid .doc{min-width:0;overflow:hidden!important}
.multi-product-grid .doc strong{display:block;min-width:0;flex:1 1 auto;white-space:nowrap!important;word-break:normal!important;overflow:visible!important;text-overflow:clip!important}
.multi-product-grid .doc-open{flex:0 0 auto}
</style>
<style id="idema-model-status">
.model-discontinued{border-color:#ff9ca0!important;background:#fff0f1!important;color:#8b1f24!important}.model-discontinued small{margin-left:6px;font-size:8px;font-weight:800;letter-spacing:.04em}
</style>