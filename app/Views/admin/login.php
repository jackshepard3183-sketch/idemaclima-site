<?php $title = 'Accesso amministrazione'; $user = null; require __DIR__ . '/_layout_start.php'; ?>
<style>
.admin-shell{display:block}
.admin-content{min-height:100vh}
.admin-content main{width:100%;max-width:none;min-height:100vh;padding:0;display:grid;place-items:center;background:
radial-gradient(circle at 16% 16%,rgba(108,173,18,.12),transparent 26%),
linear-gradient(135deg,#edf3f7 0,#f7f9fb 48%,#eef4f8 100%)}
.login-shell{width:min(1080px,calc(100% - 48px));min-height:620px;display:grid;grid-template-columns:minmax(0,.9fr) minmax(420px,1.1fr);overflow:hidden;border:1px solid #dbe5ed;border-radius:20px;background:#fff;box-shadow:0 28px 75px rgba(9,35,61,.16)}
.login-brand{position:relative;display:flex;flex-direction:column;justify-content:space-between;padding:46px 44px;background:linear-gradient(160deg,#0d3155 0,#09233d 66%,#071c31 100%);color:#fff;overflow:hidden}
.login-brand:before,.login-brand:after{content:'';position:absolute;border-radius:50%;pointer-events:none}
.login-brand:before{width:310px;height:310px;right:-150px;top:-135px;border:1px solid rgba(255,255,255,.09);box-shadow:0 0 0 55px rgba(255,255,255,.025),0 0 0 110px rgba(255,255,255,.018)}
.login-brand:after{width:180px;height:180px;left:-95px;bottom:-80px;background:rgba(108,173,18,.11)}
.login-brand-top,.login-brand-copy,.login-brand-foot{position:relative;z-index:1}
.login-logo{display:block;width:min(250px,80%);height:84px;object-fit:contain;object-position:left center;filter:brightness(0) invert(1)}
.login-kicker{display:inline-flex;align-items:center;gap:8px;margin-top:18px;color:#a8c0d4;font-size:11px;font-weight:800;letter-spacing:.13em;text-transform:uppercase}
.login-kicker:before{content:'';width:8px;height:8px;border-radius:50%;background:#76b82a;box-shadow:0 0 0 5px rgba(118,184,42,.13)}
.login-brand-copy{margin:52px 0 auto}
.login-brand-copy h2{max-width:340px;margin:0 0 14px;color:#fff;font-size:31px;line-height:1.12;letter-spacing:-.035em}
.login-brand-copy p{max-width:350px;margin:0;color:#b8c9d8;font-size:14px;line-height:1.7}
.login-brand-foot{display:flex;align-items:center;gap:9px;color:#8fa9bd;font-size:12px}
.login-brand-foot svg{width:17px;height:17px;fill:none;stroke:#76b82a;stroke-width:1.9;stroke-linecap:round;stroke-linejoin:round}
.login-form-side{display:flex;align-items:center;padding:54px 64px;background:#fff}
.login-panel{width:100%;max-width:470px;margin:0 auto;padding:0;border:0;box-shadow:none}
.login-heading{margin-bottom:30px}
.login-heading .eyebrow{display:block;margin-bottom:9px;color:#5f7890;font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
.login-heading h1{margin:0 0 9px;color:#0f2742;font-size:32px;letter-spacing:-.035em}
.login-heading p{margin:0;color:#6c7f91;font-size:14px;line-height:1.6}
.login-form{display:grid;gap:20px}
.login-form label{display:block;color:#26384a;font-size:13px;font-weight:750}
.login-form input{height:48px;margin-top:7px;padding:0 14px;border:1px solid #cbd6e1;border-radius:9px;background:#fff;font-size:15px}
.login-form input:hover{border-color:#aebdcb}
.login-form input:focus{border-color:#1c5a91;box-shadow:0 0 0 3px rgba(28,90,145,.14)}
.password-field{position:relative;margin-top:7px}
.password-field input{width:100%;margin:0;padding-right:52px}
.password-toggle{position:absolute;top:50%;right:5px;display:grid;width:40px;height:40px;padding:0;place-items:center;transform:translateY(-50%);border:0!important;border-radius:8px!important;background:transparent!important;color:#64788b!important;cursor:pointer;box-shadow:none!important}
.password-toggle:hover{background:#edf4f8!important;color:#173b61!important;transform:translateY(-50%)!important}
.password-toggle:focus-visible{outline:0;box-shadow:0 0 0 3px rgba(28,90,145,.16)!important}
.password-toggle svg{width:21px;height:21px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:2}
.password-toggle .eye-off{display:none}
.password-toggle[aria-pressed="true"] .eye-on{display:none}
.password-toggle[aria-pressed="true"] .eye-off{display:block}
.login-submit{width:100%;min-height:48px!important;margin-top:4px;border-color:#0f2742!important;border-radius:9px!important;background:linear-gradient(135deg,#0f355b,#0d2744)!important;font-size:14px!important;box-shadow:0 8px 18px rgba(15,39,66,.16)!important}
.login-submit:hover{background:linear-gradient(135deg,#153f69,#102f50)!important;box-shadow:0 10px 22px rgba(15,39,66,.2)!important}
.login-help{display:flex;align-items:center;justify-content:center;gap:7px;margin:24px 0 0;color:#8191a0;font-size:12px;text-align:center}
.login-help svg{width:15px;height:15px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
.login-panel .error{margin:0 0 22px;padding:12px 14px;border:1px solid #efc2c2;border-radius:9px;background:#fff1f1;color:#982c2c;box-shadow:none;font-size:13px}
@media(max-width:840px){
  .admin-content main{padding:22px}
  .login-shell{width:min(100%,620px);min-height:0;grid-template-columns:1fr}
  .login-brand{min-height:210px;padding:30px 32px}
  .login-logo{width:210px;height:62px}
  .login-kicker{margin-top:10px}
  .login-brand-copy{margin:28px 0 0}
  .login-brand-copy h2{max-width:none;font-size:25px}
  .login-brand-copy p{display:none}
  .login-brand-foot{display:none}
  .login-form-side{padding:38px 34px 42px}
}
@media(max-width:520px){
  .admin-content main{align-items:start;padding:0;background:#fff}
  .login-shell{width:100%;border:0;border-radius:0;box-shadow:none}
  .login-brand{min-height:185px;padding:26px 24px;border-radius:0 0 22px 22px}
  .login-logo{width:185px;height:54px}
  .login-brand-copy{margin:22px 0 0}
  .login-brand-copy h2{font-size:22px}
  .login-form-side{padding:32px 24px 40px}
  .login-heading{margin-bottom:25px}
  .login-heading h1{font-size:28px}
}
</style>

<section class="login-shell" aria-label="Accesso amministrazione IDEMA">
  <aside class="login-brand" aria-hidden="true">
    <div class="login-brand-top">
      <img class="login-logo" src="/idemaclima/public/brand-assets/idema-logo-nero.png.php" alt="">
      <span class="login-kicker">Backend amministrativo</span>
    </div>
    <div class="login-brand-copy">
      <h2>Gestione IDEMA Clima</h2>
      <p>Prodotti, schede tecniche, garanzie, Campus, contatti e contenuti in un unico pannello riservato.</p>
    </div>
    <div class="login-brand-foot">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2"/><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M12 14v3"/></svg>
      <span>Area protetta · Accesso riservato</span>
    </div>
  </aside>

  <div class="login-form-side">
    <div class="login-panel">
      <header class="login-heading">
        <span class="eyebrow">IDEMA CLIMA</span>
        <h1>Accedi al backend</h1>
        <p>Inserisci le credenziali del tuo account amministrativo.</p>
      </header>

      <?php if ($error): ?><div class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

      <form class="login-form" method="post" action="/idemaclima/admin/login">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">

        <label>
          Email o username
          <input name="login" autocomplete="username" required autofocus>
        </label>

        <label for="admin-password">
          Password
          <div class="password-field">
            <input id="admin-password" type="password" name="password" autocomplete="current-password" required>
            <button class="password-toggle" type="button" aria-label="Mostra password" aria-controls="admin-password" aria-pressed="false">
              <svg class="eye-on" viewBox="0 0 24 24" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-off" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 3 18 18"/><path d="M10.6 5.2A10.8 10.8 0 0 1 12 5c6.5 0 10 7 10 7a17.8 17.8 0 0 1-2.1 3.1M6.2 6.2C3.5 8.1 2 12 2 12s3.5 7 10 7a9.8 9.8 0 0 0 4.1-.9"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
          </div>
        </label>

        <button class="btn login-submit" type="submit">Accedi al backend</button>
      </form>

      <p class="login-help">
        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.7 2c-1 .6-1.5 1.1-1.5 2"/><path d="M12 17h.01"/></svg>
        <span>Utilizza esclusivamente le credenziali amministrative autorizzate.</span>
      </p>
    </div>
  </div>
</section>

<script nonce="<?= htmlspecialchars(\App\Core\Security::nonce(), ENT_QUOTES, 'UTF-8') ?>">
(()=>{const button=document.querySelector('.password-toggle'),input=document.getElementById('admin-password');if(!button||!input)return;button.addEventListener('click',()=>{const show=input.type==='password';input.type=show?'text':'password';button.setAttribute('aria-pressed',show?'true':'false');button.setAttribute('aria-label',show?'Nascondi password':'Mostra password');input.focus()})})();
</script>
<?php require __DIR__ . '/_layout_end.php'; ?>
