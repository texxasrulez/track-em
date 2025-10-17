<?php
use TrackEm\Core\Security;
use TrackEm\Core\I18n;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
$err = (string)($error ?? ($_GET['err'] ?? ''));
$msg = (string)($_GET['msg'] ?? '');
?>
<div class="card" style="max-width:460px;margin:40px auto;">
  <h3><?= I18n::t('login_title','Sign in to Track Em') ?></h3>
  <?php if ($err !== ''): ?>
    <div class="error" style="margin:8px 0"><?= h($err) ?></div>
  <?php endif; ?>
  <?php if ($msg !== ''): ?>
    <div class="success" style="margin:8px 0"><?= h($msg) ?></div>
  <?php endif; ?>

  <form class="form" method="post" action="?p=login">
    <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>

    <div class="form-grid">
      <div class="form-label"><span><?= I18n::t('login_username','Username') ?></span></div>
      <div class="form-ctl">
        <input type="text" name="username" autocomplete="username" required />
      </div>

      <div class="form-label"><span><?= I18n::t('login_password','Password') ?></span></div>
      <div class="form-ctl">
        <input type="password" name="password" autocomplete="current-password" required />
      </div>
    </div>

    <div class="actions actions--left" style="margin-top:12px">
      <button type="submit" class="btn btn--primary"><?= I18n::t('login_signin','Sign In') ?></button>
      <a class="btn btn--ghost" href="?p=help"><?= I18n::t('login_help','Help') ?></a>
    </div>
  </form>
</div>
