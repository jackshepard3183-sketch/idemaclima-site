<section class="hero"><div class="wrap"><div class="crumbs">Home / Garanzia</div><h1>Estensione di garanzia</h1><p>Registra il prodotto e allega la documentazione richiesta.</p></div></section>
<section class="content"><div class="wrap" style="max-width:920px">
<?php if (!empty($errors)): ?><div class="card" style="border-color:#ef4444;margin-bottom:18px"><strong>Controlla i dati inseriti</strong><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" action="/garanzia" enctype="multipart/form-data" class="card">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
<div class="grid">
<label>Modello *<select name="model_id" required><option value="">Seleziona</option><?php foreach ($models as $model): ?><option value="<?= (int)$model['id'] ?>" <?= (string)($old['model_id'] ?? '') === (string)$model['id'] ? 'selected' : '' ?>><?= e($model['product_name'].' - '.$model['code']) ?></option><?php endforeach; ?></select></label>
<label>Data fattura *<input type="date" name="invoice_date" value="<?= e($old['invoice_date'] ?? '') ?>" required></label>
<label>Numero seriale unità esterna *<input name="outdoor_serial" value="<?= e($old['outdoor_serial'] ?? '') ?>" required></label>
<label>Seriali unità interne<textarea name="indoor_serials" rows="4" placeholder="Un seriale per riga"><?= e($old['indoor_serials'] ?? '') ?></textarea></label>
<label>Nome *<input name="customer_first_name" value="<?= e($old['customer_first_name'] ?? '') ?>" required></label>
<label>Cognome *<input name="customer_last_name" value="<?= e($old['customer_last_name'] ?? '') ?>" required></label>
<label>Codice fiscale / P.IVA *<input name="fiscal_code" value="<?= e($old['fiscal_code'] ?? '') ?>" required></label>
<label>Email *<input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required></label>
<label>Telefono<input name="phone" value="<?= e($old['phone'] ?? '') ?>"></label>
<label>Indirizzo *<input name="address" value="<?= e($old['address'] ?? '') ?>" required></label>
<label>CAP *<input name="postal_code" value="<?= e($old['postal_code'] ?? '') ?>" required></label>
<label>Città *<input name="city" value="<?= e($old['city'] ?? '') ?>" required></label>
<label>Provincia *<input name="province" maxlength="2" value="<?= e($old['province'] ?? '') ?>" required></label>
<label>Regione *<input name="region" value="<?= e($old['region'] ?? '') ?>" required></label>
<label>Fattura di acquisto *<input type="file" name="invoice_file" accept=".pdf,.jpg,.jpeg,.png" required><small>PDF, JPG o PNG - max 15 MB</small></label>
<label>Documentazione F-GAS<input type="file" name="fgas_file" accept=".pdf,.jpg,.jpeg,.png"><small>Potrebbe essere obbligatoria in base al prodotto.</small></label>
</div>
<label style="display:flex;gap:10px;align-items:flex-start;margin-top:20px"><input type="checkbox" name="privacy" value="1" style="width:auto" <?= isset($old['privacy']) ? 'checked' : '' ?> required><span>Ho letto l’informativa privacy e acconsento al trattamento dei dati per la gestione della richiesta di garanzia. *</span></label>
<button class="btn" type="submit" style="margin-top:20px">Invia richiesta</button>
</form>
</div></section>
