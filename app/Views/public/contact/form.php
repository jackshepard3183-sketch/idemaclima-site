<section class="hero"><div class="wrap"><div class="crumbs"><a href="/">Home</a> / Contatti</div><h1>Contatti</h1><p>Invia una richiesta al team IDEMA Clima.</p></div></section>
<section class="content"><div class="wrap"><div class="panel" style="max-width:900px">
<?php foreach($errors as $error): ?><div class="error"><?= e($error) ?></div><?php endforeach; ?>
<form method="post" enctype="multipart/form-data">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<div class="hp" aria-hidden="true"><label>Sito web<input name="company_website" tabindex="-1" autocomplete="off"></label></div>
<div class="formgrid">
<label>Nome<input name="first_name" maxlength="120" required value="<?= e((string)($old['first_name']??'')) ?>"></label>
<label>Cognome<input name="last_name" maxlength="120" required value="<?= e((string)($old['last_name']??'')) ?>"></label>
<label>Regione<input name="region" maxlength="120" required value="<?= e((string)($old['region']??'')) ?>"></label>
<label>Provincia<input name="province" maxlength="8" required value="<?= e((string)($old['province']??'')) ?>"></label>
<label>Città<input name="city" maxlength="120" required value="<?= e((string)($old['city']??'')) ?>"></label>
<label>Telefono<input name="phone" maxlength="50" value="<?= e((string)($old['phone']??'')) ?>"></label>
<label>Email<input type="email" name="email" maxlength="190" required value="<?= e((string)($old['email']??'')) ?>"></label>
<label>Conferma email<input type="email" name="email_confirm" maxlength="190" required value="<?= e((string)($old['email_confirm']??'')) ?>"></label>
<label class="full">Oggetto<input name="subject" maxlength="220" required value="<?= e((string)($old['subject']??'')) ?>"></label>
<label class="full">Messaggio<textarea name="message" rows="7" maxlength="10000" required><?= e((string)($old['message']??'')) ?></textarea></label>
<label class="full">Allegato facoltativo<input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"><small>PDF, immagini o documenti Office - massimo 10 MB.</small></label>
<label class="full check"><input type="checkbox" name="privacy" value="1" required <?= isset($old['privacy'])?'checked':'' ?>> Accetto l’informativa privacy.</label>
</div><p style="margin-top:20px"><button class="btn" type="submit">Invia richiesta</button></p>
</form></div></div></section>
