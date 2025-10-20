<?php
use TrackEm\Core\Security;
use TrackEm\Core\Theme;
use TrackEm\Core\I18n;

$themes = Theme::list();
$active = Theme::activeId();
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }
?>
<div class="card">
  <h3><?= I18n::t('themes','Themes') ?></h3>
  <p><?= I18n::t('theme_description','Preview temporarily (cookie) or activate to persist for everyone.') ?></p>

  <?php if (!$themes): ?>
    <div class="error" style="margin-top:8px"><?= I18n::t('theme_error','No theme CSS files found in') ?> <code><?= I18n::t('theme_error_2','assets/themes/') ?></code>.</div>
  <?php else: ?>
    <div class="theme-grid">
      <?php foreach ($themes as $t):
        $pal = $t['palette'] ?? ['bg'=>'#0f1318','muted'=>'#121923','accent'=>'#4ea1ff'];
      ?>
        <div class="card theme-card">
          <div class="theme-card__head">
            <strong class="theme-card__title"><?= h($t['name']) ?></strong>
            <?php if ($t['id'] === $active): ?><span class="badge"><?= I18n::t('active','Active') ?></span><?php endif; ?>
          </div>

          <div class="swatches">
            <span class="sw" style="background:<?= h($pal['bg']) ?>"></span>
            <span class="sw" style="background:<?= h($pal['muted']) ?>"></span>
            <span class="sw" style="background:<?= h($pal['accent']) ?>"></span>
          </div>

          <div class="actions-row">
            <a class="button btn" href="?p=admin.themes&preview=<?= h($t['id']) ?>"><?= I18n::t('preview','Preview') ?></a>
            <a class="button btn" href="<?= h($t['href']) ?>" target="_blank" rel="noopener"><?= I18n::t('css','CSS') ?></a>
          </div>

          <form class="form" method="post">
            <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>
            <input type="hidden" name="action" value="activate"/>
            <input type="hidden" name="theme_id" value="<?= h($t['id']) ?>"/>
            <button type="submit" class="button btn btn--primary" <?= $t['id']===$active ? 'disabled' : '' ?>>
              Activate
            </button>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
