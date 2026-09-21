<?php require __DIR__.'/_layout_start.php'; ?>
<style>
.participant-courses{margin:6px 0 0;padding-left:18px}.participant-courses li{margin:3px 0}.participant-table td{vertical-align:top}.participant-table th:nth-child(1){width:18%}.participant-table th:nth-child(2){width:22%}.participant-table th:nth-child(3){width:18%}.participant-table th:nth-child(4){width:9%}.participant-table th:nth-child(5){width:33%}
@media(max-width:900px){.participant-table,.participant-table tbody,.participant-table tr,.participant-table td{display:block;width:100%}.participant-table thead{position:absolute;width:1px;height:1px;overflow:hidden}.participant-table tr{margin-bottom:14px;border:1px solid var(--border);border-radius:12px;background:#fff}.participant-table td{display:grid;grid-template-columns:120px minmax(0,1fr);gap:10px;padding:10px 12px}.participant-table td::before{content:attr(data-label);font-weight:700}}
</style>
<div class="toolbar">
  <div><h1>Campus - Anagrafica iscritti</h1><p class="muted">Elenco generale delle persone e storico dei corsi associati.</p></div>
  <a class="btnlink" href="/idemaclima/admin/campus/registrations">Iscrizioni per corso</a>
</div>
<form method="get" class="panel" style="margin-bottom:18px"><label>Cerca per nominativo, email, telefono, azienda o profilo<input type="search" name="q" value="<?=htmlspecialchars($search,ENT_QUOTES,'UTF-8')?>"></label><button class="btn" type="submit" style="margin-top:12px">Cerca</button></form>
<div class="panel"><strong><?=count($rows)?> anagrafiche visualizzate</strong><p class="muted" style="margin-bottom:0">Le anagrafiche sono distinte per nome, cognome ed email, così persone diverse che condividono una casella aziendale non vengono unite automaticamente.</p></div>
<div class="table-wrap"><table class="participant-table"><thead><tr><th>Nominativo</th><th>Contatti</th><th>Azienda / Profilo</th><th>Iscrizioni</th><th>Corsi</th></tr></thead><tbody>
<?php foreach($rows as $row): ?>
<tr>
  <td data-label="Nominativo"><strong><?=htmlspecialchars(trim($row['first_name'].' '.$row['last_name']),ENT_QUOTES,'UTF-8')?></strong><?=!empty($row['possible_duplicate'])?'<br><span class="badge badge-warning">POSSIBILE DUPLICATO</span>':''?></td>
  <td data-label="Contatti"><?=htmlspecialchars((string)$row['email'],ENT_QUOTES,'UTF-8')?><?=!empty($row['phone'])?'<br>'.htmlspecialchars((string)$row['phone'],ENT_QUOTES,'UTF-8'):''?></td>
  <td data-label="Azienda / Profilo"><?=htmlspecialchars((string)($row['company']?:'DATO NON DISPONIBILE'),ENT_QUOTES,'UTF-8')?><br><span class="muted"><?=htmlspecialchars((string)($row['role']?:'DATO NON DISPONIBILE'),ENT_QUOTES,'UTF-8')?></span></td>
  <td data-label="Iscrizioni"><strong><?=(int)$row['registrations_count']?></strong><br><span class="muted"><?=(int)$row['courses_count']?> corsi</span></td>
  <td data-label="Corsi"><ul class="participant-courses"><?php foreach(explode('||',(string)$row['courses']) as $course):?><li><?=htmlspecialchars($course,ENT_QUOTES,'UTF-8')?></li><?php endforeach;?></ul></td>
</tr>
<?php endforeach; ?>
<?php if(!$rows):?><tr><td colspan="5" class="muted">Nessuna anagrafica corrisponde alla ricerca.</td></tr><?php endif;?>
</tbody></table></div>
<?php require __DIR__.'/_layout_end.php'; ?>
