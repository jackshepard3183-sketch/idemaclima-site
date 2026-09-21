<?php require __DIR__.'/_layout_start.php';
$profileOptions=['INSTALLATORE'=>'Installatore','PROGETTISTA'=>'Progettista','CENTRO ASSISTENZA TECNICA'=>'Centro assistenza tecnica','CLIENTE PRIVATO'=>'Cliente privato','ALTRO'=>'Altro'];
$currentRole=mb_strtoupper(trim((string)($participant['role']??'')),'UTF-8');
?>
<div class="toolbar"><div><h1>Dettaglio iscritto</h1><p class="muted">Correggi l’anagrafica senza modificare i corsi associati.</p></div><a class="btnlink" href="/idemaclima/admin/campus/participants">Torna all’anagrafica</a></div>
<?php if(!empty($_GET['saved'])):?><div class="success">Anagrafica aggiornata in tutte le iscrizioni collegate.</div><?php endif;?>
<?php foreach($errors as $error):?><div class="error"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endforeach;?>
<form class="panel" method="post" action="/idemaclima/admin/campus/participants/update">
  <input type="hidden" name="_csrf" value="<?=htmlspecialchars($csrf,ENT_QUOTES,'UTF-8')?>"><input type="hidden" name="id" value="<?=(int)$participant['id']?>">
  <div class="formgrid">
    <label>Nome<input class="uppercase-field" name="first_name" maxlength="120" required autocapitalize="characters" value="<?=htmlspecialchars((string)$participant['first_name'],ENT_QUOTES,'UTF-8')?>"></label>
    <label>Cognome<input class="uppercase-field" name="last_name" maxlength="120" required autocapitalize="characters" value="<?=htmlspecialchars((string)$participant['last_name'],ENT_QUOTES,'UTF-8')?>"></label>
    <label>Email<input type="email" name="email" required value="<?=htmlspecialchars((string)$participant['email'],ENT_QUOTES,'UTF-8')?>"></label>
    <label>Telefono<input name="phone" value="<?=htmlspecialchars((string)($participant['phone']??''),ENT_QUOTES,'UTF-8')?>"></label>
    <label>Azienda<input class="uppercase-field" name="company" maxlength="190" autocapitalize="characters" value="<?=htmlspecialchars((string)($participant['company']??''),ENT_QUOTES,'UTF-8')?>"></label>
    <label>Profilo<select name="role" required><option value="">Seleziona profilo</option><?php foreach($profileOptions as $value=>$label):?><option value="<?=htmlspecialchars($value,ENT_QUOTES,'UTF-8')?>" <?=$currentRole===$value?'selected':''?>><?=htmlspecialchars($label,ENT_QUOTES,'UTF-8')?></option><?php endforeach;?><?php if($currentRole!==''&&!isset($profileOptions[$currentRole])):?><option value="<?=htmlspecialchars($currentRole,ENT_QUOTES,'UTF-8')?>" selected><?=htmlspecialchars($currentRole,ENT_QUOTES,'UTF-8')?> (valore storico)</option><?php endif;?></select></label>
  </div>
  <p class="muted">Nome, cognome e azienda vengono inseriti e salvati in MAIUSCOLO; l’email in minuscolo. Il profilo utilizza le stesse opzioni del modulo di iscrizione.</p><button class="btn" type="submit">Salva correzioni</button>
</form>
<div class="panel"><h2>Corsi e iscrizioni associate</h2><div class="table-wrap"><table><thead><tr><th>Data</th><th>Corso</th><th>Stato</th><th>Presenza</th><th>Attestato</th></tr></thead><tbody>
<?php foreach($registrations as $row):?><tr><td><?=empty($row['starts_at'])?'DATA NON DISPONIBILE':date('d/m/Y',strtotime((string)$row['starts_at']))?></td><td><strong><?=htmlspecialchars((string)$row['event_title'],ENT_QUOTES,'UTF-8')?></strong><?=!empty($row['archived'])?'<br><span class="badge">ARCHIVIATO</span>':''?></td><td><?=['registered'=>'ISCRITTO','confirmed'=>'CONFERMATO','waitlist'=>'LISTA D’ATTESA','cancelled'=>'ANNULLATO'][$row['status']]??htmlspecialchars((string)$row['status'],ENT_QUOTES,'UTF-8')?></td><td><?=$row['attended']===null?'—':((int)$row['attended']?'PRESENTE':'ASSENTE')?></td><td><?=!empty($row['certificate_number'])?htmlspecialchars((string)$row['certificate_number'],ENT_QUOTES,'UTF-8'):'NON EMESSO'?></td></tr><?php endforeach;?>
<?php if(!$registrations):?><tr><td colspan="5" class="muted">Ricarica la pagina per visualizzare i corsi collegati.</td></tr><?php endif;?>
</tbody></table></div></div>
<style>.uppercase-field{text-transform:uppercase}</style>
<script>document.querySelectorAll('.uppercase-field').forEach(function(field){field.addEventListener('input',function(){var start=this.selectionStart,end=this.selectionEnd;this.value=this.value.toLocaleUpperCase('it-IT');if(start!==null&&end!==null)this.setSelectionRange(start,end);});});</script>
<?php require __DIR__.'/_layout_end.php'; ?>
