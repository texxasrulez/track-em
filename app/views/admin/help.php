<?php
use TrackEm\Core\Theme;
?>
<div class="page page-help theme-<?= Theme::activeId() ?>">
  <style>
    /* ---- Theme variables scoped to the help page container ---- */
    .page-help.theme-light {
      --te-bg: #ffffff;
      --te-fg: #0f172a;
      --te-link: #0b5ee8;
      --te-muted: #475569;
      --te-border: #e5e7eb;
      --te-code-bg: #f4f6fb;
      --te-code-fg: #0f172a;
      --te-btn-bg: #111111;
      --te-btn-fg: #f5f5f5;
      --te-btn-border: #000000;
      --te-btn-bg-hover: #1c1c1c;
    }
    .page-help.theme-dark {
      --te-bg: #0b1220;
      --te-fg: #e5e7eb;
      --te-link: #93c5fd;
      --te-muted: #94a3b8;
      --te-border: #1f2937;
      --te-code-bg: #101827;
      --te-code-fg: #e5e7eb;
      --te-btn-bg: #111111;
      --te-btn-fg: #f5f5f5;
      --te-btn-border: #000000;
      --te-btn-bg-hover: #1c1c1c;
    }

    /* ---- Page base (follows active theme) ---- */
    .page-help { color: var(--te-fg); }
    .page-help .card { background: var(--te-bg); border: 1px solid var(--te-border); border-radius: 10px; }
    .page-help a { color: var(--te-link); }
    .page-help h1 a, .page-help h2 a, .page-help h3 a, .page-help h4 a, .page-help h5 a, .page-help h6 a {
      color: var(--te-link);
      text-underline-offset: 2px;
    }
    .page-help .muted { color: var(--te-muted); }

    /* ---- Code blocks ---- */
    .page-help code, .page-help pre {
      background: var(--te-code-bg) !important;
      color: var(--te-code-fg) !important;
      border: 1px solid var(--te-border);
      border-radius: 6px;
    }
    .page-help pre { padding: .75rem .9rem; overflow: auto; }

    /* ---- Copy button (always dark for contrast) ---- */
    .page-help .te-copy-btn,
    .page-help .btn-copy,
    .page-help button.btn-copy {
      background: var(--te-btn-bg) !important;
      color: var(--te-btn-fg) !important;
      border: 1px solid var(--te-btn-border) !important;
      border-radius: 4px;
      padding: 2px 8px;
      cursor: pointer;
      font-size: 0.8rem;
    }
    .page-help .te-copy-btn:hover,
    .page-help .btn-copy:hover,
    .page-help button.btn-copy:hover { background: var(--te-btn-bg-hover); }

    /* Strong override for any inline-styled copy button injected by scripts */
    .page-help button[style*="background: rgba(255, 255, 255"],
    .page-help button[style*="color: rgb(255, 255, 255"] {
      background: var(--te-btn-bg) !important;
      color: var(--te-btn-fg) !important;
      border-color: var(--te-btn-border) !important;
    }
    .page-help button[style*="background: rgba(255, 255, 255"]:hover { filter: brightness(1.05); }

    /* ---- Help iframe: always light inside box, regardless of theme ---- */
    .page-help .help-iframe { 
      background: #ffffff !important;
      color: #111111 !important;
      border: 1px solid var(--te-border);
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
