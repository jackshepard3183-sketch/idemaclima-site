<?php require __DIR__ . '/_layout_start.php'; ?>
<div class="toolbar"><div><h1><?= htmlspecialchars($title) ?></h1><p class="muted">Importazione storica idempotente: conserva ID e data WPForms, non invia email e salta automaticamente i record già presenti.</p></div><a class="btnlink" href="/idemaclima/admin/<?= $kind === 'contacts' ? 'contacts' : 'incentives' ?>">Torna all’elenco</a></div>
<div class="panel">
  <form id="wpforms-import" method="post" enctype="multipart/form-data" action="/idemaclima/admin/<?= $kind ?>/import/run">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf) ?>">
    <label>File JSON in ordine di importazione<input id="manifests" type="file" name="manifest" accept="application/json,.json" multiple required></label>
    <p class="muted">Puoi selezionare più file: saranno caricati uno alla volta nell’ordine del nome. Dimensione massima 2 MB per file.</p>
    <button class="btn" type="submit">Avvia importazione</button>
  </form>
</div>
<div class="panel" style="margin-top:18px"><strong>Esito</strong><pre id="import-result" style="white-space:pre-wrap;overflow-wrap:anywhere;margin-bottom:0">In attesa.</pre></div>
<script>
(()=>{const form=document.getElementById('wpforms-import'),input=document.getElementById('manifests'),out=document.getElementById('import-result'),button=form.querySelector('button[type=submit]');form.addEventListener('submit',async event=>{event.preventDefault();const files=[...input.files].sort((a,b)=>a.name.localeCompare(b.name,'it',{numeric:true}));if(!files.length)return;button.disabled=true;let imported=0,skipped=0,errors=[];for(const [index,file] of files.entries()){out.textContent=`Caricamento ${index+1}/${files.length}: ${file.name}`;const data=new FormData();data.append('_csrf',form.elements._csrf.value);data.append('manifest',file,file.name);try{const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin'});const text=await response.text();let result;try{result=JSON.parse(text)}catch{throw new Error(text||`HTTP ${response.status}`)}imported+=Number(result.imported||0);skipped+=Number(result.skipped||0);errors=errors.concat((result.errors||[]).map(error=>`${file.name}: ${error}`));}catch(error){errors.push(`${file.name}: ${error.message}`);}}out.textContent=JSON.stringify({imported,skipped,errors},null,4);button.disabled=false;});})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
