<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar">
  <div><h1>Campus - Eventi</h1><p class="muted"><?=($audience??'')==='cat'?'Eventi riservati ai CAT':(($audience??'')==='public'?'Eventi aperti':'Tutti gli eventi')?></p></div>
  <a class="btnlink" href="/idemaclima/admin/campus/events/form">Nuovo evento</a>
</div>
<div class="quick-list" style="grid-template-columns:repeat(3,minmax(0,180px));margin-bottom:18px">
  <a href="/idemaclima/admin/campus/events"><span>Tutti</span></a>
  <a href="/idemaclima/admin/campus/events?audience=public"><span>Eventi aperti</span></a>
  <a href="/idemaclima/admin/campus/events?audience=cat"><span>Eventi CAT</span></a>
</div>
<div class="table-wrap"><table><thead><tr><th>Evento</th><th>Tipologia</th><th>Data</th><th>Iscrizioni</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>
<?php foreach($events as $event): ?>
  <tr>
    <td><strong><?=htmlspecialchars($event['title'],ENT_QUOTES,'UTF-8')?></strong><?=!empty($event['category'])?'<br><span class="muted">'.htmlspecialchars($event['category'],ENT_QUOTES,'UTF-8').'</span>':''?></td>
    <td><span class="badge"><?=$event['audience']==='cat'?'CAT':'Aperto'?></span></td>
    <td><?=htmlspecialchars(date('d/m/Y H:i',strtotime((string)$event['starts_at'])),ENT_QUOTES,'UTF-8')?></td>
    <td><a href="/idemaclima/admin/campus/registrations?event_id=<?=(int)$event['id']?>"><?=(int)$event['registrations']?></a></td>
    <td><?=(int)$event['cancelled']?'Annullato':((int)$event['published']?((int)$event['registration_open']?'Pubblicato · iscrizioni aperte':'Pubblicato · iscrizioni chiuse'):'Bozza')?></td>
    <td><a href="/idemaclima/admin/campus/events/form?id=<?=(int)$event['id']?>">Modifica</a><form method="post" action="/idemaclima/admin/campus/events/duplicate" style="display:inline;margin-left:8px"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="id" value="<?=(int)$event['id']?>"><button type="submit" class="link-button">Duplica</button></form></td>
  </tr>
<?php endforeach; ?>
<?php if(!$events): ?><tr><td colspan="6" class="muted">Nessun evento disponibile.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>
