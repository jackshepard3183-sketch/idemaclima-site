<?php require __DIR__ . '/_layout_start.php'; ?>
<style>
.import-help{margin:0 0 18px}.import-picker{display:grid;gap:14px}.import-picker input[type=file]{padding:14px;background:#f8fafc}.queue-actions{display:flex;flex-wrap:wrap;gap:10px;align-items:center}.queue-actions .btn[disabled],.queue-actions button[disabled]{cursor:not-allowed;opacity:.55}.import-summary{display:grid;grid-template-columns:repeat(4,minmax(120px,1fr));gap:10px;margin:18px 0}.import-stat{padding:12px;border:1px solid var(--line);border-radius:10px;background:#f8fafc}.import-stat strong{display:block;font-size:22px}.progress-track{height:12px;overflow:hidden;border-radius:999px;background:#e8edf2;margin:14px 0}.progress-bar{width:0;height:100%;background:var(--blue);transition:width .2s ease}.queue-table td:first-child{width:54px;text-align:center}.queue-table td:last-child{width:150px}.queue-file{font-weight:700}.queue-detail{display:block;margin-top:3px;color:var(--muted);font-size:12px}.queue-order{display:inline-flex;gap:4px}.queue-order button{width:34px;height:34px;padding:0;border:1px solid var(--line);border-radius:7px;background:#fff}.state-pending{color:var(--muted)}.state-running{color:#15568a;font-weight:700}.state-success{color:#287238;font-weight:700}.state-error{color:#991b1b;font-weight:700}.import-log{display:none;margin-top:16px;padding:14px;border-radius:10px;background:#0f2742;color:#eaf2f8;white-space:pre-wrap;overflow-wrap:anywhere}.import-log.is-visible{display:block}@media(max-width:760px){.import-summary{grid-template-columns:1fr 1fr}.queue-actions>*{width:100%}.queue-table{min-width:720px}}
</style>
<?php
$isContacts = $kind === 'contacts';
$entityLabel = $isContacts ? 'Contatti' : 'Richieste EasyTool';
$entityLabelLower = $isContacts ? 'contatti' : 'richieste EasyTool';
$returnPath = $isContacts ? 'contacts' : 'incentives';
?>
<div class="toolbar"><div><h1><?= htmlspecialchars($title) ?></h1><p class="muted">Carica più lotti e importali automaticamente, uno alla volta.</p></div><a class="btnlink" href="/idemaclima/admin/<?= $returnPath ?>">Torna all’elenco</a></div>
<div class="panel">
  <p class="import-help"><strong>Seleziona tutti i file JSON da importare.</strong> Saranno ordinati automaticamente per nome. Ogni lotto viene inviato separatamente; gli ID WPForms già acquisiti vengono ignorati, le date originali e gli avvisi di verifica vengono conservati e non vengono inviate email.</p>
  <div class="import-picker">
    <label>Manifest JSON
      <input id="manifest-files" type="file" accept="application/json,.json" multiple required>
    </label>
    <div class="queue-actions">
      <button class="btn" id="start-import" type="button" disabled>Avvia importazione</button>
      <button id="sort-import" type="button" disabled>Riordina per nome</button>
      <span class="muted" id="selection-note">Nessun file selezionato.</span>
    </div>
  </div>
  <div class="import-summary" aria-live="polite">
    <div class="import-stat"><span>File completati</span><strong id="files-complete">0/0</strong></div>
    <div class="import-stat"><span><?= htmlspecialchars($entityLabel) ?> importati</span><strong id="items-imported">0</strong></div>
    <div class="import-stat"><span>Già presenti</span><strong id="items-skipped">0</strong></div>
    <div class="import-stat"><span>Errori</span><strong id="items-errors">0</strong></div>
  </div>
  <div class="progress-track" role="progressbar" aria-label="Avanzamento importazione" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><div class="progress-bar" id="progress-bar"></div></div>
  <div class="table-wrap"><table class="queue-table"><thead><tr><th>#</th><th>File</th><th>Ordine</th><th>Stato</th></tr></thead><tbody id="import-queue"><tr><td colspan="4" class="empty">Seleziona uno o più file JSON.</td></tr></tbody></table></div>
  <pre class="import-log" id="import-log" aria-live="polite"></pre>
</div>
<script>
(()=>{
const csrf=<?= json_encode($csrf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const endpoint=<?= json_encode('/idemaclima/admin/' . $returnPath . '/import/run', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const entityLabel=<?= json_encode($entityLabelLower, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const input=document.getElementById('manifest-files'),start=document.getElementById('start-import'),sortButton=document.getElementById('sort-import'),body=document.getElementById('import-queue'),note=document.getElementById('selection-note'),bar=document.getElementById('progress-bar'),track=bar.parentElement,log=document.getElementById('import-log');
const complete=document.getElementById('files-complete'),imported=document.getElementById('items-imported'),skipped=document.getElementById('items-skipped'),errors=document.getElementById('items-errors');
let queue=[],running=false,current=0,totals={imported:0,skipped:0,errors:0};
const naturalSort=()=>queue.sort((a,b)=>a.file.name.localeCompare(b.file.name,'it',{numeric:true,sensitivity:'base'}));
const escapeText=value=>String(value==null?'':value);
const setProgress=()=>{const done=queue.filter(item=>item.status==='success').length,percent=queue.length?Math.round(done/queue.length*100):0;complete.textContent=done+'/'+queue.length;imported.textContent=totals.imported;skipped.textContent=totals.skipped;errors.textContent=totals.errors;bar.style.width=percent+'%';track.setAttribute('aria-valuenow',String(percent));};
const render=()=>{
 body.textContent='';
 if(!queue.length){const row=document.createElement('tr'),cell=document.createElement('td');cell.colSpan=4;cell.className='empty';cell.textContent='Seleziona uno o più file JSON.';row.appendChild(cell);body.appendChild(row);}
 queue.forEach((item,index)=>{
  const row=document.createElement('tr'),number=document.createElement('td'),fileCell=document.createElement('td'),order=document.createElement('td'),state=document.createElement('td');number.textContent=String(index+1);
  const name=document.createElement('span'),detail=document.createElement('span');name.className='queue-file';name.textContent=item.file.name;detail.className='queue-detail';detail.textContent=Math.max(1,Math.round(item.file.size/1024))+' KB';fileCell.append(name,detail);
  const controls=document.createElement('span');controls.className='queue-order';[['↑',-1,'Sposta prima'],['↓',1,'Sposta dopo']].forEach(spec=>{const button=document.createElement('button');button.type='button';button.textContent=spec[0];button.setAttribute('aria-label',spec[2]+' '+item.file.name);button.disabled=running||(spec[1]<0?index===0:index===queue.length-1);button.addEventListener('click',()=>{const other=index+spec[1];[queue[index],queue[other]]=[queue[other],queue[index]];render()});controls.appendChild(button)});order.appendChild(controls);
  const labels={pending:'In attesa',running:'Importazione…',success:'Completato',error:'Errore'};state.className='state-'+item.status;state.textContent=labels[item.status]||item.status;if(item.detail){const d=document.createElement('span');d.className='queue-detail';d.textContent=item.detail;state.appendChild(d);}row.append(number,fileCell,order,state);body.appendChild(row);
 });
 start.disabled=running||!queue.length||current>=queue.length;sortButton.disabled=running||queue.length<2;input.disabled=running;note.textContent=queue.length?queue.length+' file selezionati.':'Nessun file selezionato.';setProgress();
};
const parseResponse=async response=>{const text=await response.text();try{return JSON.parse(text)}catch{return {imported:0,skipped:0,errors:[text||('Errore HTTP '+response.status)]}}};
const importOne=async item=>{const data=new FormData();data.append('_csrf',csrf);data.append('manifest',item.file,item.file.name);const response=await fetch(endpoint,{method:'POST',body:data,credentials:'same-origin',headers:{Accept:'application/json'}});const result=await parseResponse(response);if(!response.ok||!Array.isArray(result.errors))throw new Error((result.errors||['Risposta di importazione non valida']).join(' | '));return result;};
const run=async()=>{
 if(running||!queue.length)return;running=true;start.textContent='Importazione in corso…';log.classList.remove('is-visible');log.textContent='';render();
 for(;current<queue.length;current++){const item=queue[current];item.status='running';item.detail='';render();try{const result=await importOne(item),fileErrors=result.errors||[];totals.imported+=Number(result.imported||0);totals.skipped+=Number(result.skipped||0);totals.errors+=fileErrors.length;if(fileErrors.length)throw new Error(fileErrors.join(' | '));item.status='success';item.detail=Number(result.imported||0)+' importati, '+Number(result.skipped||0)+' già presenti';render();}catch(error){item.status='error';item.detail=error instanceof Error?error.message:escapeText(error);running=false;start.textContent='Riprova dal file bloccato';log.textContent='Importazione interrotta su '+item.file.name+'\n'+item.detail;log.classList.add('is-visible');render();return;}}
 running=false;start.textContent='Importazione completata';log.textContent='Tutti i file sono stati elaborati nell’ordine indicato: '+entityLabel+'.';log.classList.add('is-visible');render();
};
input.addEventListener('change',()=>{const unique=new Map();Array.from(input.files||[]).filter(file=>/\.json$/i.test(file.name)).forEach(file=>unique.set(file.name+'|'+file.size+'|'+file.lastModified,file));queue=Array.from(unique.values()).map(file=>({file,status:'pending',detail:''}));naturalSort();running=false;current=0;totals={imported:0,skipped:0,errors:0};start.textContent='Avvia importazione';log.classList.remove('is-visible');render();});
sortButton.addEventListener('click',()=>{naturalSort();render()});start.addEventListener('click',run);window.addEventListener('beforeunload',event=>{if(!running)return;event.preventDefault();event.returnValue=''});render();
})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
