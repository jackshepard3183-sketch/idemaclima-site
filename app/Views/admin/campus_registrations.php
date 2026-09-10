<?php require __DIR__.'/_layout_start.php'; ?>
<div class="toolbar">
  <div><h1>Campus - Iscrizioni</h1><p class="muted">Partecipanti, lista d’attesa e presenze.</p></div>
  <a class="btnlink" href="/idemaclima/admin/campus/registrations/export?<?=http_build_query(array_filter(['event_id'=>(int)($filters['event_id']??0),'cat_account_id'=>(int)($filters['cat_account_id']??0),'status'=>(string)($filters['status']??'')]))?>">Esporta CSV / Excel</a>
</div>
<form method="get" class="panel" style="margin-bottom:18px">
  <div class="formgrid">
    <label>Evento<select name="event_id"><option value="">Tutti gli eventi</option><?php foreach($events as $event):?><option value="<?=(int)$event['id']?>" <?=(int)($filters['event_id']??0)===(int)$event['id']?'selected':''?>><?=htmlspecialchars(date('d/m/Y',strtotime((string)$event['starts_at'])).' · '.$event['title'],ENT_QUOTES,'UTF-8')?></option><?php endforeach;?></select></label>
    <label>Stato<select name="status"><option value="">Tutti gli stati</option><?php foreach(['registered'=>'Iscritto','confirmed'=>'Confermato','waitlist'=>'Lista d’attesa','cancelled'=>'Annullato'] as $value=>$label):?><option value="<?=$value?>" <?=($filters['status']??'')===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label>
  </div>
  <button class="btn" type="submit" style="margin-top:14px">Filtra</button>
</form>
<div class="table-wrap"><table><thead><tr><th>Evento</th><th>Partecipante</th><th>Contatti</th><th>Azienda</th><th>Stato</th><th>Presenza</th><th>Attestato</th><th>Gestione</th></tr></thead><tbody>
<?php foreach($rows as $row): ?>
  <tr>
    <td><strong><?=htmlspecialchars($row['event_title'],ENT_QUOTES,'UTF-8')?></strong><br><span class="badge"><?=$row['audience']==='cat'?'CAT':'Aperto'?></span></td>
    <td><?=htmlspecialchars($row['first_name'].' '.$row['last_name'],ENT_QUOTES,'UTF-8')?></td>
    <td><?=htmlspecialchars($row['email'],ENT_QUOTES,'UTF-8')?><?=!empty($row['phone'])?'<br>'.htmlspecialchars($row['phone'],ENT_QUOTES,'UTF-8'):''?></td>
    <td><?=htmlspecialchars((string)($row['cat_company']?:$row['company']),ENT_QUOTES,'UTF-8')?></td>
    <td><?=['registered'=>'Iscritto','confirmed'=>'Confermato','waitlist'=>'Lista d’attesa','cancelled'=>'Annullato'][$row['status']]??htmlspecialchars($row['status'],ENT_QUOTES,'UTF-8')?></td>
    <td><?=$row['attended']===null?'—':((int)$row['attended']?'Presente':'Assente')?></td>
    <td><?=!empty($row['certificate_number'])?htmlspecialchars((string)$row['certificate_number'],ENT_QUOTES,'UTF-8'):'Non emesso'?></td>
    <td><form method="post" action="/idemaclima/admin/campus/registrations/update"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><select name="status"><option value="registered" <?=$row['status']==='registered'?'selected':''?>>Iscritto</option><option value="confirmed" <?=$row['status']==='confirmed'?'selected':''?>>Confermato</option><option value="waitlist" <?=$row['status']==='waitlist'?'selected':''?>>Lista d’attesa</option><option value="cancelled" <?=$row['status']==='cancelled'?'selected':''?>>Annullato</option></select><select name="attended"><option value="">Presenza da definire</option><option value="1" <?=$row['attended']!==null&&(int)$row['attended']===1?'selected':''?>>Presente</option><option value="0" <?=$row['attended']!==null&&(int)$row['attended']===0?'selected':''?>>Assente</option></select><button class="btn" type="submit">Salva</button></form></td>
  </tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="muted">Nessuna iscrizione corrisponde ai filtri selezionati.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>
