<?php
use TrackEm\Core\I18n;
use TrackEm\Core\Security;
use TrackEm\Core\Theme;

I18n::boot();
$themeId = Theme::activeId();
$__p = isset($_GET['p']) ? (string)$_GET['p'] : '';

/** prefix-aware active check without PHP 8 dependency */
function te_active($p, $needle) {
  if ($p === $needle) return true;
  $len = strlen($needle);
  return ($len > 0) && strncmp($p, $needle . '.', $len + 1) === 0;
}
?>
<?php \TrackEm\Core\Security::emitSecurityHeaders(); ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Track 'Em</title>
  <link rel="stylesheet" href="assets/css/style.css"/>
  <?= Theme::cssTag() ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>

<style id="te-admin-ui">
  /* Theme variable contract (themes can override these):
     --te-surface, --te-surface-hover, --te-border, --te-text
     --te-primary-bg, --te-primary-border, --te-primary-text, --te-primary-hover
     --te-danger-bg,  --te-danger-border,  --te-danger-text,  --te-danger-hover
  */

  /* Button base — neutral variant uses surface/text/border */
  .button, .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 6px 12px;
    border: 1px solid var(--te-border);
    background: var(--te-surface);
    color: var(--te-text);
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    font-size: 14px;
    line-height: 1.2;
    transition: background .15s ease, border-color .15s ease, box-shadow .15s ease, opacity .15s ease, color .15s ease;
  }
  .button:hover, .btn:hover { background: var(--te-surface-hover); }

  .button:disabled, .btn:disabled { opacity: .55; cursor: not-allowed; }

  /* Primary variant pulls from theme primary vars */
  .btn--primary, .button.btn {
    background: var(--te-primary-bg);
    border-color: var(--te-primary-border);
    color: var(--te-primary-text);
  }
  .btn--primary:hover, .button.btn:hover {
    background: var(--te-primary-hover);
    border-color: var(--te-primary-hover);
    color: var(--te-primary-text);
  }

  /* Danger variant pulls from theme danger vars */
  .danger, .btn--danger {
    background: var(--te-danger-bg);
    border-color: var(--te-danger-border);
    color: var(--te-danger-text);
  }
  .danger:hover, .btn--danger:hover {
    background: var(--te-danger-hover);
    border-color: var(--te-danger-hover);
    color: var(--te-danger-text);
  }

  /* Block buttons */
  .btn--block { width: 100%; display: inline-flex; }

  /* Compact inputs */
  .form input[type="text"],
  .form input[type="password"],
  .form input[type="number"],
  .form select {
    width: 220px;
    display: inline-block;
    margin-right: 8px;
  }
  .form.form-inline input[type="text"],
  .form.form-inline input[type="password"],
  .form.form-inline select {
    width: 160px;
  }

  /* Table padding consistency */
  table td, table th { padding: 6px 8px; }
  .te-title { color: var(--te-primary-text, var(--te-text)) !important; transition: color 0.2s ease-in-out; }
</style>

</head>
<body class="theme-<?= htmlspecialchars($themeId, ENT_QUOTES) ?>">
  <header class="topbar">
    <a href="?p=admin"><img src="assets/images/header-logo.png" height="40px"></a><h1 class="te-title">Track 'Em</h1>
    <nav>
      <a href="?p=admin" class="<?= te_active($__p,'admin') ? 'active' : '' ?>"><?= I18n::t('nav_dashboard','Dashboard') ?></a>
      <a href="?p=admin.settings" class="<?= te_active($__p,'admin.settings') ? 'active' : '' ?>"><?= I18n::t('nav_settings','Settings') ?></a>
      <a href="?p=admin.users" class="<?= te_active($__p,'admin.users') ? 'active' : '' ?>"><?= I18n::t('nav_users','Users') ?></a>
      <a href="?p=admin.themes" class="<?= te_active($__p,'admin.themes') ? 'active' : '' ?>"><?= I18n::t('nav_themes','Themes') ?></a>
      <a href="?p=admin.plugins" class="<?= te_active($__p,'admin.plugins') ? 'active' : '' ?>"><?= I18n::t('nav_plugins','Plugins') ?></a>
      <a href="?p=admin.help" class="<?= te_active($__p,'admin.help') ? 'active' : '' ?>"><?= I18n::t('nav_help','Help') ?></a>
      <a href="?p=logout" class="right <?= te_active($__p,'logout') ? 'active' : '' ?>"><?= I18n::t('nav_logout','Logout') ?></a>
    </nav>
  </header>
  <main>
    <?php require $viewFile; ?>
  </main>
  <script>window.TE_ENDPOINT='/track.php';</script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="assets/js/app.js"></script>
<?php $__base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'); if ($__base === '/') $__base = ''; ?>
  <script>window.TE_BASE=<?php echo json_encode($__base); ?>;</script>
  <script src="assets/js/consent.js"></script>
  <script src="assets/js/dragdrop.js"></script>
</body>
</html>
