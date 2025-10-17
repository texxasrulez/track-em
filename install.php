<?php
declare(strict_types=1);

$cfgPath   = __DIR__ . '/config/config.php';
$lockPath  = __DIR__ . '/config/.installed.lock';
$schemaPath= __DIR__ . '/sql/schema.sql';
$pluginsDir= __DIR__ . '/plugins';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

/* --- session/CSRF (robust behind proxies) --- */
function csrf_boot(){
  $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
  if (session_status() === PHP_SESSION_NONE) {
    session_name('TESESS');
    session_start([
      'cookie_httponly' => true,
      'cookie_secure'   => $is_https,
      'cookie_samesite' => 'Lax',
      'cookie_path'     => '/',
      'use_strict_mode' => true,
      'use_only_cookies'=> true,
    ]);
  }
}
function csrf_token(){ csrf_boot(); $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); return $_SESSION['csrf']; }
function csrf_check($t){ csrf_boot(); return hash_equals($_SESSION['csrf'] ?? '', $t ?? ''); }

/* --- helpers --- */
function dsn(string $h, int $p, string $n): string {
  return sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $h, $p, $n);
}
function json_decode_a(string $s): array { $j=json_decode($s, true); return is_array($j)?$j:[]; }

/* --- page vars --- */
$errors  = [];
$ok      = false;
$already = is_file($cfgPath) && is_file($lockPath);
$loginUrl = (rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') ?: '') . '/index.php';

/* --- POST: run install/repair --- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  if (!csrf_check($_POST['csrf'] ?? '')) { $errors[] = 'Bad CSRF token.'; }
  $db_host = trim($_POST['db_host'] ?? '127.0.0.1');
  $db_port = (int)($_POST['db_port'] ?? 3306);
  $db_name = trim($_POST['db_name'] ?? '');
  $db_user = trim($_POST['db_user'] ?? '');
  $db_pass = (string)($_POST['db_pass'] ?? '');
  $app_theme = trim($_POST['app_theme'] ?? 'default');
  $app_lang  = trim($_POST['app_lang'] ?? 'en_US');
  $base_url  = trim($_POST['base_url'] ?? '');

  $admin_user = trim($_POST['admin_user'] ?? '');
  $admin_pass = (string)($_POST['admin_pass'] ?? '');

  if ($db_name==='') $errors[] = 'DB name required';
  if ($db_user==='') $errors[] = 'DB user required';
  if ($admin_user==='') $errors[] = 'Admin username required';
  if (strlen($admin_pass) < 8) $errors[] = 'Admin password must be at least 8 chars';

  if (!$errors) {
    try {
      $pdo = new PDO(dsn($db_host,$db_port,$db_name), $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]);

      /* 1) Run extended schema (idempotent) */
      $sql = file_get_contents($schemaPath);
      foreach (array_filter(array_map('trim', explode(";", $sql))) as $stmt) {
        if ($stmt !== '') $pdo->exec($stmt);
      }

      /* 2) Ensure admin user exists */
      $ph = password_hash($admin_pass, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM users WHERE username=?");
      $stmt->execute([$admin_user]);
      if (((int)$stmt->fetch()['c']) === 0) {
        $ins = $pdo->prepare("INSERT INTO users (username,password_hash,role) VALUES (?,?, 'admin')");
        $ins->execute([$admin_user,$ph]);
      }

      /* 3) Discover plugins and upsert rows with defaults */
      if (is_dir($pluginsDir)) {
        foreach (scandir($pluginsDir) as $d) {
          if ($d==='.' || $d==='..') continue;
          $pj = $pluginsDir . "/$d/plugin.json";
          if (!is_file($pj)) continue;
          $man = json_decode_a((string)file_get_contents($pj));
          if (empty($man['id'])) continue;
          $pid = (string)$man['id'];

          // Build default config from schema defaults
          $defaults = [];
          if (!empty($man['configSchema']['fields']) && is_array($man['configSchema']['fields'])) {
            foreach ($man['configSchema']['fields'] as $f) {
              if (!is_array($f) || empty($f['name'])) continue;
              $fname = (string)$f['name'];
              if (array_key_exists('default', $f)) $defaults[$fname] = $f['default'];
            }
          }

          // Insert if missing; keep existing config if present
          $st = $pdo->prepare("INSERT INTO plugins (id, enabled, config)
                               VALUES (?, 1, ?)
                               ON DUPLICATE KEY UPDATE id=id"); // no-op update, preserves existing
          $st->execute([$pid, json_encode($defaults, JSON_UNESCAPED_SLASHES)]);

          // If row exists but config is NULL, set to defaults
          $fix = $pdo->prepare("UPDATE plugins SET config = COALESCE(config, ?) WHERE id=?");
          $fix->execute([json_encode($defaults, JSON_UNESCAPED_SLASHES), $pid]);
        }
      }

      /* 4) Write config.php (includes geo defaults) */
      $cfg = [
        'base_url' => $base_url,
        'database' => ['host'=>$db_host,'port'=>$db_port,'name'=>$db_name,'user'=>$db_user,'pass'=>$db_pass],
        'theme'    => ['active'=>$app_theme],
        'i18n'     => ['default'=>$app_lang],
        'privacy'  => ['respect_dnt'=>true,'require_consent'=>false,'ip_anonymize'=>true,'ip_mask_bits'=>16],
        // Geo provider defaults (client code respects these)
        'geo'      => [
          'enabled'       => true,
          'provider'      => 'ip-api',
          'ip_api_base'   => 'http://ip-api.com/json',
          'timeout_sec'   => 0.8,
          'max_lookups'   => 15
        ],
      ];
      if (!is_dir(dirname($cfgPath))) mkdir(dirname($cfgPath), 0775, true);
      file_put_contents($cfgPath, "<?php\nreturn " . var_export($cfg, true) . ";\n");

      /* 5) Lock file */
      file_put_contents($lockPath, "installed: ".date('c')."\n");
      $ok = true;
    } catch (Throwable $e) {
      $errors[] = 'Install failed: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Track Em — Installer</title>
<style>
*{box-sizing:border-box} body{font-family:system-ui,Arial,sans-serif;background:#0b0e11;color:#e8e8e8;margin:0}
.wrapper{max-width:860px;margin:40px auto;padding:20px}
.card{background:#12161c;border:1px solid #1f2630;border-radius:12px;padding:16px}
h1,h2,h3{margin-top:0}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
label{display:block;margin:8px 0 4px}
input,select,button{width:100%;padding:10px;border-radius:8px;border:1px solid #2a3340;background:#0f1318;color:#e8e8e8}
button{cursor:pointer}
.error{background:#3b1114;border:1px solid #622027;color:#ffb4b9;padding:10px;border-radius:8px;margin:8px 0}
.success{background:#113b1e;border:1px solid #1f5f2f;color:#b9ffcc;padding:10px;border-radius:8px;margin:8px 0}
.note{opacity:.8;font-size:.95em}
.footer{margin-top:16px;display:flex;gap:8px;justify-content:flex-end}
.lock{padding:8px 10px;border-radius:8px;border:1px dashed #2a3340;margin-bottom:8px}
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <h1>Track Em — Web Installer</h1>
    <?php if ($already && !$ok): ?>
      <div class="lock">Existing install detected. You can <a href="<?= h($loginUrl) ?>">open the app</a> or re-install (non-destructive).</div>
    <?php endif; ?>
    <?php foreach ($errors as $e): ?><div class="error"><?= h($e) ?></div><?php endforeach; ?>
    <?php if ($ok): ?>
      <div class="success">Installation complete. Schema migrated, admin ensured, plugin defaults synced.</div>
      <div class="footer">
        <a href="<?= h($loginUrl) ?>"><button>Go to Login</button></a>
      </div>
    <?php else: ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
      <h2>Application</h2>
      <div class="grid">
        <div>
          <label>Base URL (optional)</label>
          <input name="base_url" placeholder="https://example.com">
        </div>
        <div>
          <label>Default Theme</label>
          <select name="app_theme">
            <option value="default">default</option>
            <option value="dark">dark</option>
          </select>
        </div>
        <div>
          <label>Default Language</label>
          <select name="app_lang">
            <option value="en_US">en_US</option>
            <option value="es_ES">es_ES</option>
          </select>
        </div>
      </div>

      <h2>Database</h2>
      <div class="grid">
        <div><label>Host</label><input name="db_host" value="127.0.0.1" required></div>
        <div><label>Port</label><input name="db_port" type="number" value="3306" required></div>
        <div><label>Database</label><input name="db_name" required></div>
        <div><label>User</label><input name="db_user" required></div>
        <div><label>Password</label><input name="db_pass" type="password"></div>
      </div>

      <h2>Admin User</h2>
      <div class="grid">
        <div><label>Username</label><input name="admin_user" required></div>
        <div><label>Password</label><input name="admin_pass" type="password" placeholder="min 8 chars" required></div>
      </div>

      <p class="note">Installer creates/updates tables and seeds plugin defaults. It does not drop existing data.</p>
      <div class="footer">
        <button type="submit">Install / Repair</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
