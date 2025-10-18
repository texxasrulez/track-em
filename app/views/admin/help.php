<?php
use TrackEm\Core\Theme;
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
    <h3><a href="#">Help</a></h3>
    <p class="muted">Built‑in HTML help is shown below.</p>
    <iframe class="help-iframe" src="docs/help.html"></iframe>
  </div>
</div>
