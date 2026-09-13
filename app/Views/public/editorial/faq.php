<style>
.faq-hero{padding:62px 0 70px;background:var(--gradient-cool);border-bottom:1px solid var(--border)}
.faq-eyebrow{display:flex;align-items:center;gap:8px;color:var(--primary-deep);font-size:12px;font-weight:600;letter-spacing:.3em;text-transform:uppercase}
.faq-hero h1{max-width:900px;margin:11px 0 18px;font-size:clamp(42px,6vw,68px);line-height:1.04}
.faq-hero p{max-width:850px;margin:0;color:var(--muted-foreground);font-size:18px;line-height:1.7}
.faq-content{padding:64px 0 96px}
.faq-layout{display:grid;grid-template-columns:minmax(260px,1fr) minmax(0,2.2fr);gap:38px;align-items:start}
.faq-aside{display:grid;gap:18px;position:sticky;top:116px}
.faq-help{padding:28px;border:1px solid var(--border);border-radius:25px;background:#fff}
.faq-help svg{width:36px;height:36px;margin-bottom:18px;fill:none;stroke:var(--primary-deep);stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
.faq-help h2,.faq-help h3{margin:0 0 9px;font-size:20px}
.faq-help p{margin:0;color:var(--muted-foreground);font-size:14px;line-height:1.7}
.faq-help .btn{margin-top:18px;padding:10px 15px;font-size:12px;box-shadow:none}
.faq-list-lovable{padding:8px 28px;border:1px solid var(--border);border-radius:26px;background:#fff}
.faq-row{border-bottom:1px solid var(--border)}.faq-row:last-child{border-bottom:0}
.faq-row summary{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:21px 4px;cursor:pointer;list-style:none;font-family:Sora,system-ui,sans-serif;font-size:17px;font-weight:600}
.faq-row summary::-webkit-details-marker{display:none}
.faq-row summary:after{content:'+';flex:0 0 auto;color:var(--primary-deep);font:500 24px Inter}
.faq-row[open] summary:after{content:'−'}
.faq-answer-lovable{padding:0 34px 22px 4px;color:var(--muted-foreground);font-size:15px;line-height:1.75}
@media(max-width:850px){.faq-layout{grid-template-columns:1fr}.faq-aside{position:static;grid-template-columns:repeat(2,1fr)}.faq-aside .faq-help:first-child{grid-column:1/-1}}
@media(max-width:600px){.faq-aside{grid-template-columns:1fr}.faq-aside .faq-help:first-child{grid-column:auto}.faq-list-lovable{padding:5px 16px}.faq-row summary{font-size:15px}}
</style>
<section class="faq-hero"><div class="wrap">
<span class="faq-eyebrow">— Domande e risposte</span>
<h1>FAQ</h1>
<p>Le risposte alle domande più frequenti su installazione, garanzia, manutenzione ed efficienza energetica dei climatizzatori IDEMA.</p>
</div></section>
<section class="faq-content"><div class="wrap faq-layout">
<aside class="faq-aside">
<div class="faq-help"><svg viewBox="0 0 24 24"><path d="M4 5h16v12H8l-4 4z"/><path d="M9.8 9a2.3 2.3 0 0 1 4.4 1c0 1.6-2.2 1.8-2.2 3"/><path d="M12 15.5h.01"/></svg><h2>Non trovi una risposta?</h2><p>Se non trovi risposta alla tua domanda sull’impianto di climatizzazione, è necessaria un’analisi del problema in loco. Rivolgiti al tuo installatore qualificato di fiducia.</p></div>
<div class="faq-help"><svg viewBox="0 0 24 24"><path d="M12 3 20 6v6c0 5-3.4 8-8 9-4.6-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/></svg><h3>Garanzia</h3><p>Termini, condizioni e certificati di garanzia.</p><a class="btn" href="/idemaclima/garanzia">Vai alla garanzia</a></div>
<div class="faq-help"><svg viewBox="0 0 24 24"><path d="M4 13a8 8 0 0 1 16 0"/><path d="M4 13v5h4v-6H4M20 13v5h-4v-6h4M16 18c0 2-2 3-4 3"/></svg><h3>Portale assistenza</h3><p>Apri una richiesta di intervento su idemaassistenza.it.</p><a class="btn" href="https://idemaassistenza.it/" target="_blank" rel="noopener">Vai al portale ↗</a></div>
</aside>
<div class="faq-list-lovable">
<?php foreach($faqs as $faq): ?>
<details class="faq-row"><summary><?=e((string)$faq['question'])?></summary><div class="faq-answer-lovable"><?=nl2br(e((string)$faq['answer']))?></div></details>
<?php endforeach; ?>
<?php if(!$faqs): ?><div class="faq-answer-lovable">Le FAQ saranno pubblicate a breve.</div><?php endif; ?>
</div>
</div></section>