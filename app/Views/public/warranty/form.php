<section class="hero"><div class="wrap"><div class="crumbs">Home / Garanzia</div><h1>Estensione di garanzia</h1><p>Registra il prodotto e allega la documentazione richiesta.</p></div></section>
<section class="content"><div class="wrap" style="max-width:920px">
<?php if (!empty($errors)): ?><div class="card" style="border-color:#ef4444;margin-bottom:18px"><strong>Controlla i dati inseriti</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/garanzia" enctype="multipart/form-data" class="card" autocomplete="on">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<div style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true"><label>Sito web azienda<input type="text" name="company_website" tabindex="-1" autocomplete="off"></label></div>
<div class="grid">
<label>Modello *<select name="model_id" required><option value="">Seleziona</option><?php foreach ($models as $model): ?><option value="<?= (int)$model['id'] ?>" <?= (string)($old['model_id'] ?? '') === (string)$model['id'] ? 'selected' : '' ?>><?= e($model['product_name'].' - '.$model['code']) ?></option><?php endforeach; ?></select></label>
<label>Data fattura *<input type="date" name="invoice_date" max="<?= e(date('Y-m-d')) ?>" value="<?= e($old['invoice_date'] ?? '') ?>" required></label>
<label>Numero seriale unità esterna *<input name="outdoor_serial" maxlength="160" value="<?= e($old['outdoor_serial'] ?? '') ?>" required></label>
<label>Seriali unità interne<textarea name="indoor_serials" rows="4" maxlength="3400" placeholder="Un seriale per riga; massimo 20"><?= e($old['indoor_serials'] ?? '') ?></textarea></label>
<label>Nome *<input name="customer_first_name" maxlength="120" value="<?= e($old['customer_first_name'] ?? '') ?>" required></label>
<label>Cognome *<input name="customer_last_name" maxlength="120" value="<?= e($old['customer_last_name'] ?? '') ?>" required></label>
<label>Codice fiscale / P.IVA *<input name="fiscal_code" maxlength="32" value="<?= e($old['fiscal_code'] ?? '') ?>" required></label>
<label>Email *<input type="email" name="email" maxlength="190" value="<?= e($old['email'] ?? '') ?>" required></label>
<label>Telefono<input name="phone" maxlength="50" inputmode="tel" value="<?= e($old['phone'] ?? '') ?>"></label>
<label>Indirizzo *<input name="address" maxlength="255" value="<?= e($old['address'] ?? '') ?>" required></label>
<label>CAP *<input name="postal_code" maxlength="12" value="<?= e($old['postal_code'] ?? '') ?>" required></label>
<label>Città *<input name="city" maxlength="120" value="<?= e($old['city'] ?? '') ?>" required></label>
<label>Provincia *<input name="province" maxlength="2" pattern="[A-Za-z]{2}" value="<?= e($old['province'] ?? '') ?>" required></label>
<label>Regione *<input name="region" maxlength="120" value="<?= e($old['region'] ?? '') ?>" required></label>
<label>Fattura di acquisto<input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png"><small>PDF, JPG o PNG - max 15 MB. L'obbligatorietà dipende dalla regola associata al modello.</small></label>
<label>Documentazione F-GAS<input type="file" name="fgas_file" accept=".pdf,.jpg,.jpeg,.png"><small>Potrebbe essere obbligatoria in base al prodotto.</small></label>
</div>
<label style="display:flex;gap:10px;align-items:flex-start;margin-top:20px"><input type="checkbox" name="privacy" value="1" style="width:auto" <?= isset($old['privacy']) ? 'checked' : '' ?> required><span>Ho letto l’informativa privacy e acconsento al trattamento dei dati per la gestione della richiesta di garanzia. *</span></label>
<button class="btn" type="submit" style="margin-top:20px">Invia richiesta</button>
</form>
</div></section>
