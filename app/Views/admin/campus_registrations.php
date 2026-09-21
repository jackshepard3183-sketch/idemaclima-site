<?php require __DIR__.'/_layout_start.php'; ?>
<style>
.registrations-wrap{overflow-x:visible}
.registrations-table{width:100%;min-width:0!important;table-layout:fixed}
.registrations-table th,.registrations-table td{padding:10px 7px;vertical-align:top;overflow-wrap:anywhere}
.registrations-table th:nth-child(1){width:22%}.registrations-table th:nth-child(2){width:12%}.registrations-table th:nth-child(3){width:17%}.registrations-table th:nth-child(4){width:13%}.registrations-table th:nth-child(5){width:8%}.registrations-table th:nth-child(6){width:8%}.registrations-table th:nth-child(7){width:8%}.registrations-table th:nth-child(8){width:12%}
.registrations-table th button{white-space:normal;text-align:left;line-height:1.15}
.registration-actions{display:grid;gap:6px;margin:0}.registration-actions select,.registration-actions .btn{width:100%;min-width:0;font-size:12px;padding:7px 6px}.registration-actions .btn{white-space:nowrap}
.course-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:0 0 18px}.course-card{display:flex;flex-direction:column;gap:8px;min-height:132px;padding:16px;border:1px solid var(--border);border-radius:12px;background:#fff;color:inherit;text-decoration:none;box-shadow:var(--shadow-sm)}.course-card:hover,.course-card.is-active{border-color:var(--primary);background:#f7fbf2}.course-card strong{line-height:1.3}.course-meta{display:flex;justify-content:space-between;gap:8px;align-items:center;margin-top:auto}.course-count{font-size:22px;font-weight:800;color:var(--primary)}
@media(max-width:1200px){.course-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:900px){.registrations-table,.registrations-table tbody,.registrations-table tr,.registrations-table td{display:block;width:100%}.registrations-table{table-layout:auto}.registrations-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}.registrations-table tr{margin-bottom:14px;border:1px solid var(--border);border-radius:12px;background:#fff;overflow:hidden}.registrations-table td{display:grid;grid-template-columns:minmax(105px,32%) minmax(0,1fr);gap:10px;border-bottom:1px solid var(--border);padding:10px 12px}.registrations-table td:last-child{border-bottom:0}.registrations-table td::before{content:attr(data-label);font-weight:700;color:var(--text)}.registration-actions{max-width:280px}}
@media(max-width:620px){.course-grid{grid-template-columns:1fr}}
</style>

<div class="toolbar">
  <div><h1>Campus - Iscrizioni</h1><p class="muted">Partecipanti, lista d’attesa e presenze.</p></div>
  <div><a class="btnlink" href="/idemaclima/admin/campus/participants">Anagrafica iscritti</a> <a class="btnlink" href="/idemaclima/admin/campus/registrations/import">Importa archivio</a> <a class="btnlink" href="/idemaclima/admin/campus/registrations/export?<?=http_build_query(array_filter(['event_id'=>(int)($filters['event_id']??0),'cat_account_id'=>(int)($filters['cat_account_id']??0),'status'=>(string)($filters['status']??'')]))?>">Esporta CSV / Excel</a></div>
</div>
<div class="course-grid">
<?php foreach($events as $event): ?>
  <a class="course-card <?=(int)($filters['event_id']??0)===(int)$event['id']?'is-active':''?>" href="/idemaclima/admin/campus/registrations?event_id=<?=(int)$event['id']?>">
    <span class="muted"><?=empty($event['starts_at'])?'DATA NON DISPONIBILE':date('d/m/Y',strtotime((string)$event['starts_at']))?></span>
    <strong><?=htmlspecialchars((string)$event['title'],ENT_QUOTES,'UTF-8')?></strong>
    <span class="course-meta"><span class="badge"><?=$event['audience']==='cat'?'CAT':'APERTO'?><?=!empty($event['archived'])?' · ARCHIVIATO':''?></span><span class="course-count"><?=(int)$event['registrations']?></span></span>
  </a>
<?php endforeach; ?>
</div>
<form method="get" class="panel" style="margin-bottom:18px">
  <div class="formgrid">
    <label>Evento<select name="event_id"><option value="">Tutti gli eventi</option><?php foreach($events as $event):?><option value="<?=(int)$event['id']?>" <?=(int)($filters['event_id']??0)===(int)$event['id']?'selected':''?>><?=htmlspecialchars((empty($event['starts_at'])?'Data non disponibile':date('d/m/Y',strtotime((string)$event['starts_at']))).' · '.$event['title'],ENT_QUOTES,'UTF-8')?></option><?php endforeach;?></select></label>
    <label>Stato<select name="status"><option value="">Tutti gli stati</option><?php foreach(['registered'=>'Iscritto','confirmed'=>'Confermato','waitlist'=>'Lista d’attesa','cancelled'=>'Annullato'] as $value=>$label):?><option value="<?=$value?>" <?=($filters['status']??'')===$value?'selected':''?>><?=$label?></option><?php endforeach;?></select></label>
  </div>
  <button class="btn" type="submit" style="margin-top:14px">Filtra</button>
</form>
<div class="table-wrap registrations-wrap"><table class="registrations-table"><thead><tr><th>Evento</th><th>Partecipante</th><th>Contatti</th><th>Azienda / Profilo</th><th>Stato</th><th>Presenza</th><th>Attestato</th><th>Gestione</th></tr></thead><tbody>
<?php foreach($rows as $row): ?>
  <tr>
    <td data-label="Evento"><strong><?=htmlspecialchars($row['event_title'],ENT_QUOTES,'UTF-8')?></strong><br><span class="badge"><?=$row['audience']==='cat'?'CAT':'Aperto'?></span></td>
    <td data-label="Partecipante"><?=htmlspecialchars($row['first_name'].' '.$row['last_name'],ENT_QUOTES,'UTF-8')?></td>
    <td data-label="Contatti"><?=htmlspecialchars($row['email'],ENT_QUOTES,'UTF-8')?><?=!empty($row['phone'])?'<br>'.htmlspecialchars($row['phone'],ENT_QUOTES,'UTF-8'):''?></td>
    <td data-label="Azienda / Profilo"><?=htmlspecialchars((string)($row['cat_company']?:$row['company']),ENT_QUOTES,'UTF-8')?><br><span class="muted">Profilo: <?=htmlspecialchars((string)($row['role']??'')!==''?(string)$row['role']:'Dato non disponibile',ENT_QUOTES,'UTF-8')?></span></td>
    <td data-label="Stato"><?=['registered'=>'Iscritto','confirmed'=>'Confermato','waitlist'=>'Lista d’attesa','cancelled'=>'Annullato'][$row['status']]??htmlspecialchars($row['status'],ENT_QUOTES,'UTF-8')?></td>
    <td data-label="Presenza"><?=$row['attended']===null?'—':((int)$row['attended']?'Presente':'Assente')?></td>
    <td data-label="Attestato"><?=!empty($row['certificate_number'])?htmlspecialchars((string)$row['certificate_number'],ENT_QUOTES,'UTF-8'):'Non emesso'?></td>
    <td data-label="Gestione"><form class="registration-actions" method="post" action="/idemaclima/admin/campus/registrations/update"><input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><select name="status"><option value="registered" <?=$row['status']==='registered'?'selected':''?>>Iscritto</option><option value="confirmed" <?=$row['status']==='confirmed'?'selected':''?>>Confermato</option><option value="waitlist" <?=$row['status']==='waitlist'?'selected':''?>>Lista d’attesa</option><option value="cancelled" <?=$row['status']==='cancelled'?'selected':''?>>Annullato</option></select><select name="attended"><option value="">Presenza da definire</option><option value="1" <?=$row['attended']!==null&&(int)$row['attended']===1?'selected':''?>>Presente</option><option value="0" <?=$row['attended']!==null&&(int)$row['attended']===0?'selected':''?>>Assente</option></select><button class="btn" type="submit">Salva</button></form></td>
  </tr>
<?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="8" class="muted">Nessuna iscrizione corrisponde ai filtri selezionati.</td></tr><?php endif; ?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>
