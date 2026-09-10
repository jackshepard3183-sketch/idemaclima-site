<?php $old=$old??[];$errors=$errors??[]; ?>
<section class="inc-section" aria-labelledby="easytool-request-title">
  <h2 id="easytool-request-title">Richiedi la consulenza</h2>
  <p>Compila i dati essenziali: il team IDEMA ti ricontatterà per valutare la pratica.</p>
  <?php if(isset($_GET['sent'])): ?><div class="easy-alert easy-success" role="status">Richiesta inviata correttamente. Ti ricontatteremo appena possibile.</div><?php endif; ?>
  <?php if($errors): ?><div class="easy-alert easy-error" role="alert"><strong>Controlla i dati inseriti:</strong><ul><?php foreach($errors as $error): ?><li><?=e((string)$error)?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <form class="easy-form" method="post" action="/idemaclima/detrazioni-e-incentivi/consulenza-energetica">
    <input type="hidden" name="_csrf" value="<?=e((string)($csrf??''))?>">
    <label class="easy-hp" aria-hidden="true">Sito web<input name="company_website" tabindex="-1" autocomplete="off"></label>
    <div class="easy-grid">
      <label>Nome *<input name="first_name" maxlength="120" autocomplete="given-name" required value="<?=e((string)($old['first_name']??''))?>"></label>
      <label>Cognome *<input name="last_name" maxlength="120" autocomplete="family-name" required value="<?=e((string)($old['last_name']??''))?>"></label>
      <label class="easy-full">Azienda <input name="company" maxlength="190" autocomplete="organization" value="<?=e((string)($old['company']??''))?>"></label>
      <label>Regione *<select id="warranty-region" name="region" required data-selected="<?=e((string)($old['region']??''))?>"><option value="">Seleziona la regione</option></select></label>
      <label>Provincia *<select id="warranty-province" name="province" required data-selected="<?=e((string)($old['province']??''))?>" disabled><option value="">Seleziona la provincia</option></select></label>
      <label>Città *<select id="warranty-city" name="city" required data-selected="<?=e((string)($old['city']??''))?>" disabled><option value="">Seleziona la città</option></select></label>
      <label>CAP *<select id="warranty-postal-code" name="postal_code" required data-selected="<?=e((string)($old['postal_code']??''))?>" disabled><option value="">Seleziona il CAP</option></select></label>
      <label>Telefono *<input type="tel" name="phone" maxlength="50" autocomplete="tel" required value="<?=e((string)($old['phone']??''))?>"></label>
      <label>Profilo *<select name="role" required><option value="">Seleziona</option><?php foreach(['Installatore','Progettista','Centro assistenza tecnica','Cliente privato','Altro'] as $role): ?><option value="<?=e($role)?>" <?=($old['role']??'')===$role?'selected':''?>><?=e($role)?></option><?php endforeach; ?></select></label>
      <label>Email *<input type="email" name="email" maxlength="190" autocomplete="email" required value="<?=e((string)($old['email']??''))?>"></label>
      <label>Conferma email *<input type="email" name="email_confirm" maxlength="190" required value="<?=e((string)($old['email_confirm']??''))?>"></label>
      <label class="easy-full easy-check"><input type="checkbox" name="privacy" value="1" required <?=isset($old['privacy'])?'checked':''?>><span>Ho letto l’<a href="https://www.iubenda.com/privacy-policy/38092343" target="_blank" rel="noopener">informativa privacy</a> e acconsento al trattamento dei dati per la gestione della richiesta. *</span></label>
    </div>
    <div class="easy-actions"><button class="btn" type="submit">Invia richiesta</button></div>
  </form>
</section>
<script src="/idemaclima/public/warranty-locations-2026.js" defer></script>
