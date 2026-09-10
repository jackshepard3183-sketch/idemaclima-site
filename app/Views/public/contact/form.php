<style>
.section-label{color:var(--primary-deep);font-size:12px;font-weight:700;letter-spacing:.3em;text-transform:uppercase}.contact-layout{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(280px,.75fr);gap:28px;align-items:start}.contact-form{padding:34px;border:1px solid var(--border);border-radius:28px;background:#fff}.contact-form .formgrid{gap:19px}.contact-form label{font-weight:600}.contact-form input,.contact-form select,.contact-form textarea{width:100%;margin-top:7px}.contact-form small{display:block;margin-top:7px;color:var(--muted-foreground);font-weight:400}.contact-form .btn{margin-top:20px;border:0;cursor:pointer}.contact-info{position:sticky;top:105px;padding:32px;border-radius:28px;background:var(--gradient-cool)}.contact-info h2{font-size:27px}.contact-info p{color:var(--muted-foreground);line-height:1.7}.contact-error{margin-bottom:14px;padding:14px 17px;border:1px solid #ef4444;border-radius:15px;background:#fef2f2;color:#991b1b}.check{display:flex!important;gap:10px;align-items:flex-start}.check input{width:auto;margin-top:4px}@media(max-width:820px){.contact-layout{grid-template-columns:1fr}.contact-info{position:static}.contact-form{padding:24px}}
</style>
<section class="hero"><div class="wrap"><span class="section-label">Parliamo del tuo progetto</span><h1>Contatti</h1><p>Invia una richiesta al team IDEMA CLIMA®.</p></div></section>
<section class="content"><div class="wrap"><div class="contact-layout"><div class="contact-form">
<?php foreach($errors as $error): ?><div class="contact-error"><?= e($error) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data" autocomplete="on">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<div class="hp" aria-hidden="true"><label>Sito web<input name="company_website" tabindex="-1" autocomplete="off"></label></div>
<div class="formgrid">
<label>Nome *<input name="first_name" maxlength="120" required value="<?= e((string)($old['first_name']??'')) ?>"></label>
<label>Cognome *<input name="last_name" maxlength="120" required value="<?= e((string)($old['last_name']??'')) ?>"></label>
<label>Regione *<select name="region" id="warranty-region" data-initial="<?= e((string)($old['region']??'')) ?>" required><option value="<?= e((string)($old['region']??'')) ?>"><?= e(($old['region']??'')!==''?(string)$old['region']:'Seleziona regione') ?></option></select></label>
<label>Provincia *<select name="province" id="warranty-province" data-initial="<?= e((string)($old['province']??'')) ?>" required disabled><option value="<?= e((string)($old['province']??'')) ?>"><?= e(($old['province']??'')!==''?(string)$old['province']:'Seleziona provincia') ?></option></select></label>
<label>Città *<select name="city" id="warranty-city" data-initial="<?= e((string)($old['city']??'')) ?>" required disabled><option value="<?= e((string)($old['city']??'')) ?>"><?= e(($old['city']??'')!==''?(string)$old['city']:'Seleziona città') ?></option></select></label>
<label>CAP *<select name="postal_code" id="warranty-postal-code" data-initial="<?= e((string)($old['postal_code']??'')) ?>" required disabled><option value="<?= e((string)($old['postal_code']??'')) ?>"><?= e(($old['postal_code']??'')!==''?(string)$old['postal_code']:'Seleziona CAP') ?></option></select></label>
<label>Telefono *<input name="phone" maxlength="50" inputmode="tel" required value="<?= e((string)($old['phone']??'')) ?>"></label>
<label>Email *<input type="email" name="email" maxlength="190" required value="<?= e((string)($old['email']??'')) ?>"></label>
<label>Conferma email *<input type="email" name="email_confirm" maxlength="190" required value="<?= e((string)($old['email_confirm']??'')) ?>"></label>
<label class="full">Oggetto *<input name="subject" maxlength="220" required value="<?= e((string)($old['subject']??'')) ?>"></label>
<label class="full">Messaggio *<textarea name="message" rows="7" maxlength="10000" required><?= e((string)($old['message']??'')) ?></textarea></label>
<label class="full">Allegato facoltativo<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"><small>PDF, immagini o documenti Office - massimo 10 MB.</small></label>
<label class="full check"><input type="checkbox" name="privacy" value="1" required <?= isset($old['privacy'])?'checked':'' ?>> <span>Ho letto l’<a href="https://www.iubenda.com/privacy-policy/38092343" target="_blank" rel="noopener">informativa privacy</a> e acconsento al trattamento dei dati per la gestione della richiesta. *</span></label>
</div>
<button class="btn" type="submit">Invia richiesta</button>
</form></div><aside class="contact-info"><span class="section-label">IDEMA CLIMA®</span><h2>Competenza e assistenza diretta.</h2><p>Descrivi la tua richiesta con il maggior numero possibile di dettagli. Il team potrà indirizzarla rapidamente alla funzione corretta.</p></aside></div></div></section>
<script src="/idemaclima/public/warranty-locations-2026.js" defer></script>
