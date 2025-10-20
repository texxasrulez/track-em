<?php
use TrackEm\Core\Security;
use TrackEm\Core\Theme;
use TrackEm\Core\I18n;
?>
<div class="page page-help theme-<?= Theme::activeId() ?>">
  <style>
    /* ---- Help iframe: always light inside box, regardless of theme ---- */
    .page-help .help-iframe { 
      border: 1px solid var(--border);
      border-radius: 8px;
      width: 100%;
      height: 60vh;
    }
  </style>

  <div class="card" style="padding: 1rem;">
    <h3><a href="#"><?= I18n::t('help','Help') ?></a></h3>
    <p class="muted"><?= I18n::t('builtin_help','Built‑in HTML help is shown below.') ?></p>
    <iframe class="help-iframe" src="docs/help.html"></iframe>
  </div>
</div>
