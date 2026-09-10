(function(){
  function normalizeGroups(root){
    if(!root)return root;
    var found={};
    root.querySelectorAll('.doc-group').forEach(function(group){
      var h=group.querySelector('h3'),name=h?h.textContent:'';
      var key=/manuale/i.test(name)?'MANUALI':(/scheda/i.test(name)?'SCHEDE TECNICHE':(/rese/i.test(name)?'TABELLE RESE':(/detraz/i.test(name)?'DETRAZIONI FISCALI':(/conto/i.test(name)?'CONTO TERMICO':name.toUpperCase()))));
      if(found[key]){group.querySelectorAll('.doc').forEach(function(doc){found[key].querySelector('.doc-list').appendChild(doc)});group.remove();}
      else{found[key]=group;if(h)h.textContent=key;}
    });
    var order=['SCHEDE TECNICHE','TABELLE RESE','DETRAZIONI FISCALI','CONTO TERMICO','MANUALI'];
    order.concat(Object.keys(found).filter(function(key){return order.indexOf(key)<0;})).forEach(function(key){
      if(!found[key])return;
      var group=found[key],count=group.querySelectorAll('.doc').length,badge=group.querySelector('.doc-count');
      group.classList.add('group-'+key.toLowerCase().replace(/\s+/g,'-'));
      if(badge)badge.textContent=count+' '+(count===1?'file':'file');
      group.querySelectorAll('.doc strong').forEach(function(title){
        var clean=title.textContent.trim().replace(/^.*?\s-\s/,'');
        title.textContent=clean;
      });
      root.appendChild(group);
    });
    return root;
  }
  document.querySelectorAll('.product-item').forEach(function(item){
    var status=item.querySelector('.product-status');
    if(status){
      if(/fuori|discontinued|non disponibile/i.test(status.textContent+' '+status.className)){status.textContent='ⓘ FUORI CATALOGO';status.className='product-status discontinued';}
      else status.remove();
    }
    var meta=item.querySelector('.product-meta');
    if(meta){var match=meta.textContent.match(/(\d+)\s+schede/i),n=match?match[1]:'0';meta.innerHTML='<span>▧ '+n+' schede</span><b>·</b><span>SCHEDE TECNICHE · TABELLE RESE · DETRAZIONI FISCALI · CONTO TERMICO · MANUALI</span>';}
    var open=item.querySelector('.product-open');if(open)open.innerHTML='<svg viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>';
  });
  var dp=document.querySelector('[data-panel="documents"]'),dt=document.querySelector('.documents-title'),dg=document.querySelector('.document-groups');
  if(dp&&dt&&dg){dp.innerHTML='';dp.appendChild(dt);dp.appendChild(dg);}
  normalizeGroups(dg);
  var productData=document.querySelector('#lovable-product-data');
  if(productData){
    var quick=document.querySelector('.quick');
    if(quick)quick.innerHTML='<div><span>❄&nbsp; Raffrescam.</span><strong>'+productData.dataset.coolMin+'–'+productData.dataset.coolMax+' kW</strong></div><div><span>♨&nbsp; Riscaldam.</span><strong>'+productData.dataset.heatMin+'–'+productData.dataset.heatMax+' kW</strong></div><div><span>◇&nbsp; Garanzia</span><strong>'+productData.dataset.warranty+'</strong></div>';
    var summary=document.querySelector('.summary');
    if(summary&&!summary.querySelector('.documents-cta')){var button=document.createElement('button');button.className='documents-cta';button.type='button';button.textContent='Documenti & PDF';button.addEventListener('click',function(){var tab=document.querySelector('[data-tab="documents"]');if(tab){tab.click();tab.scrollIntoView({behavior:'smooth',block:'center'});}});summary.appendChild(button);}
  }
  document.querySelectorAll('.tab-button').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('.tab-button,.tab-panel').forEach(function(x){x.classList.remove('active')});b.classList.add('active');var p=document.querySelector('[data-panel="'+b.dataset.tab+'"]');if(p)p.classList.add('active')})});
  document.querySelectorAll('.product-item:not([data-special])').forEach(function(i){i.addEventListener('toggle',function(){if(!i.open||i.dataset.loaded)return;i.dataset.loaded='1';var p=i.querySelector('.product-panel'),pageLink=p.querySelector('.product-page-link'),linkHtml=pageLink?pageLink.outerHTML:'';p.innerHTML=linkHtml+'<div class="panel-loading">Caricamento documenti…</div>';fetch(i.dataset.url,{credentials:'same-origin'}).then(function(r){return r.text()}).then(function(h){var d=new DOMParser().parseFromString(h,'text/html'),x=d.querySelector('.product-description'),g=normalizeGroups(d.querySelector('.document-groups'));p.innerHTML=linkHtml+'<div class="inline-docs">'+(x?x.outerHTML:'')+(g?g.outerHTML:'<p>Documentazione su richiesta.</p>')+'</div>'}).catch(function(){p.innerHTML=linkHtml+'<div class="panel-loading">Impossibile caricare i documenti. Riprova.</div>'})})});
})();
