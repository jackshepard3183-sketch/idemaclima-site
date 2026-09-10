<style>.cat-head{display:flex;justify-content:space-between;gap:20px;align-items:center;margin-bottom:34px;padding:20px 24px;border:1px solid var(--border);border-radius:20px;background:var(--muted)}.cat-head form,.cat-head .btn{margin:0}.cat-section+.cat-section{margin-top:48px}.cat-section>h2{margin:0 0 18px}.event-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}.event-card{display:flex;flex-direction:column;padding:24px;border:1px solid var(--border);border-radius:24px;background:#fff}.event-card h3{margin:9px 0;font-size:21px}.event-card p{color:var(--muted-foreground)}.event-state{display:inline-flex;width:max-content;margin-top:14px;padding:5px 9px;border-radius:999px;background:rgba(145,208,37,.15);color:var(--primary-deep);font-size:12px;font-weight:700}.event-state.cancelled{background:#fee2e2;color:#991b1b}.event-more{margin-top:auto;padding-top:14px;color:var(--primary-deep);font-weight:700}.cat-empty{padding:22px;border:1px solid var(--border);border-radius:18px;background:var(--muted);color:var(--muted-foreground)}@media(max-width:900px){.event-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:620px){.cat-head{align-items:flex-start;flex-direction:column}.event-grid{grid-template-columns:1fr}.event-card{padding:20px}.cat-section+.cat-section{margin-top:36px}}</style>
<section class="hero"><div class="wrap"><span class="section-label">Formazione tecnica riservata</span><h1>Campus CAT</h1><p>Eventi, aggiornamenti e storico delle attività dedicate ai Centri Assistenza Tecnica IDEMA.</p></div></section>
<?php
$upcoming=array_values(array_filter($events,static fn(array $event):bool=>strtotime((string)$event['starts_at'])>=time()&&!(int)$event['cancelled']));
$history=array_values(array_filter($events,static fn(array $event):bool=>strtotime((string)$event['starts_at'])<time()||(int)$event['cancelled']));
$renderEvent=static function(array $event):void{$cancelled=(int)$event['cancelled']===1; ?>
  <a class="event-card" href="/idemaclima/campus/cat/<?=e($event['slug'])?>">
    <span class="section-label"><?=e($event['category']?:'Evento CAT')?></span>
    <h3><?=e($event['title'])?></h3>
    <span class="meta"><?=e(date('d/m/Y · H:i',strtotime((string)$event['starts_at'])))?><?=!empty($event['location'])?' · '.e($event['location']):''?></span>
    <?php if(!empty($event['short_description'])):?><p><?=e($event['short_description'])?></p><?php endif;?>
    <?php if(!empty($event['user_status'])):?><span class="event-state"><?=['registered'=>'Iscritto','confirmed'=>'Confermato','waitlist'=>'Lista d’attesa','cancelled'=>'Iscrizione annullata'][$event['user_status']]??e($event['user_status'])?></span><?php elseif($cancelled):?><span class="event-state cancelled">Evento annullato</span><?php elseif(strtotime((string)$event['starts_at'])<time()):?><span class="event-state">Evento concluso</span><?php endif;?>
    <?php if($event['user_attended']!==null):?><span class="event-state"><?=(int)$event['user_attended']?'Presente':'Assente'?></span><?php endif;?>
    <?php if(!empty($event['certificate_number'])):?><span class="event-state">Attestato <?=e($event['certificate_number'])?></span><?php elseif((int)($event['user_attended']??0)===1):?><span class="meta">Attestato non ancora disponibile</span><?php endif;?>
    <span class="event-more">Apri evento →</span>
  </a>
<?php }; ?>
<section class="content"><div class="wrap">
  <div class="cat-head"><div><strong><?=e($catUser['company_name'])?></strong><br><span class="meta"><?=e($catUser['contact_first_name'].' '.$catUser['contact_last_name'])?></span></div><form method="post" action="/idemaclima/campus/cat/logout"><input type="hidden" name="_csrf" value="<?=e($csrf)?>"><button class="btn" type="submit">Esci</button></form></div>
  <section class="cat-section"><h2>Prossimi eventi</h2><?php if($upcoming):?><div class="event-grid"><?php foreach($upcoming as $event)$renderEvent($event);?></div><?php else:?><div class="cat-empty">Nessun evento CAT in programma.</div><?php endif;?></section>
  <section class="cat-section"><h2>Storico attività</h2><?php if($history):?><div class="event-grid"><?php foreach(array_reverse($history) as $event)$renderEvent($event);?></div><?php else:?><div class="cat-empty">Lo storico degli eventi CAT è ancora vuoto.</div><?php endif;?></section>
</div></section>
