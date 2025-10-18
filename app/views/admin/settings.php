<?php
use TrackEm\Core\Security;
use TrackEm\Core\Theme;
use TrackEm\Core\I18n;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

/** Expect $cfg, $langs, $flash_geo from controller (as before) */
$geo = $cfg['geo'] ?? [];
$prov = (string)($geo['provider'] ?? 'ip-api');
$mmdb = (string)($geo['mmdb_path'] ?? (dirname(__DIR__,2).'/data/GeoLite2-City.mmdb'));
$BASE = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$API  = ($BASE === '' ? '' : $BASE) . '/index.php?p=';
?>

<div class="grid">
  <div class="card card--settings">
    <h3>Dashboard</h3>
    <form method="post" action="?p=admin.settings">
      <input type="hidden" name="section" value="dashboard">
            <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>
<label>Default row limit (Recent/Geo):
        <input type="number" style="width:95%" min="10" max="10000" step="10" name="dash_row_limit"
               value="<?= (int)($cfg['dashboard']['row_limit'] ?? 200) ?>">
      </label>
      <label><input type="checkbox" name="dash_show_icons" value="1"
             <?= !empty($cfg['dashboard']['show_icons']) ? 'checked' : '' ?>> Show row icons</label>
      <label><input type="checkbox" name="dash_ip_tooltips" value="1"
             <?= !empty($cfg['dashboard']['ip_tooltips']) ? 'checked' : '' ?>> Enable IP hover popups</label>
      <button type="submit" class="btn">Save Dashboard</button>
    </form>
  </div>

  <!-- GEO SETTINGS -->
  <div class="card card--settings">
    <h3>Geo Settings</h3>

    <?php if (!empty($flash_geo)): ?>
      <div class="<?= $flash_geo['ok'] ? 'success' : 'error' ?>" style="margin-bottom:10px">
        <?= $flash_geo['ok']
            ? ('GeoLite download ok: ' . h($flash_geo['path'] ?? ''))
            : ('GeoLite download failed: ' . h($flash_geo['msg'] ?? '')) ?>
      </div>
    <?php endif; ?>

    <form class="form" method="post">
      <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>
      <input type="hidden" name="action" value="save_geo"/>

      <div class="form-grid">
        <!-- Enable -->
        <div class="form-label">
          <span>Enable geolocation lookups</span>
        </div>
        <div class="form-ctl">
          <label class="inline">
            <input type="checkbox" name="geo_enabled" <?= !empty($geo['enabled']) ? 'checked' : '' ?> />
            <span><?= I18n::t('enabled','Enabled') ?></span>
          </label>
        </div>

        <!-- Provider -->
        <div class="form-label"><span>Provider</span></div>
        <div class="form-ctl">
          <select name="geo_provider" id="geo_provider" onchange="geoToggle()">
            <option value="ip-api" <?= $prov==='ip-api'?'selected':'' ?>>ip-api (free)</option>
            <option value="maxmind_local" <?= $prov==='maxmind_local'?'selected':'' ?>>MaxMind GeoLite2 (local DB)</option>
            <option value="maxmind_web" <?= $prov==='maxmind_web'?'selected':'' ?>>MaxMind GeoIP2 (web service)</option>
          </select>
        </div>

        <!-- ip-api -->
        <div class="form-label ipapi-box"><span>ip-api Base URL</span></div>
        <div class="form-ctl  ipapi-box">
          <input type="text" name="ip_api_base" value="<?= h($geo['ip_api_base'] ?? 'http://ip-api.com/json/') ?>"/>
        </div>

        <!-- MaxMind Local -->
        <div class="form-label mm-local-box"><span>MMDB Path</span></div>
        <div class="form-ctl  mm-local-box">
          <input type="text" name="mmdb_path" value="<?= h($mmdb) ?>"/>
          <div class="note" style="margin-top:6px">Requires a free MaxMind account & license key for downloads.</div>
        </div>

        <!-- MaxMind Web -->
        <div class="form-label mm-web-box"><span>MaxMind Account ID</span></div>
        <div class="form-ctl  mm-web-box">
          <input type="text" name="mm_account_id" value="<?= h($geo['mm_account_id'] ?? '') ?>"/>
        </div>
        <div class="form-label mm-web-box"><span>MaxMind License Key</span></div>
        <div class="form-ctl  mm-web-box">
          <input type="text" name="mm_license_key" value="<?= h($geo['mm_license_key'] ?? '') ?>"/>
        
<div class="form-label mm-local-box"><span>GeoLite2 Database</span></div>
<div class="form-ctl  mm-local-box">
  <button type="button" class="btn" id="btn-mmdb-download">Download / Update GeoLite2</button>
  <div class="note">Uses your MaxMind license key to download GeoLite2-City.mmdb into the configured path.</div>
  <pre id="mmdb-download-out" style="margin-top:6px;max-height:160px;overflow:auto;background:#111;color:#9f9;padding:8px;border-radius:6px;display:none"></pre>
</div>

</div>

        <!-- Timeouts / caps -->
        <div class="form-label"><span>Timeout (sec)</span></div>
        <div class="form-ctl">
          <input type="number" step="0.1" name="geo_timeout" value="<?= h((string)($geo['timeout_sec'] ?? 0.8)) ?>"/>
        </div>
        <div class="form-label"><span>Max lookups per request</span></div>
        <div class="form-ctl">
          <input type="number" name="geo_maxlookups" value="<?= h((string)($geo['max_lookups'] ?? 50)) ?>"/>
        </div>
      </div>

      <div class="actions actions--left" style="margin-top:10px">
        <button type="submit" class="btn btn--primary">Save Geo Settings</button>
        <button type="button" class="btn" onclick="downloadMMDB()">Download GeoLite2 DB</button>
        <button type="button" class="btn" onclick="testGeo()">Test Provider</button>
      </div>
    </form>

    <pre id="geo-test-output" style="margin-top:10px; padding:10px; background:#0f1318; border:1px solid var(--border); border-radius:8px; max-height:240px; overflow:auto; display:none"></pre>
  </div>
</div>

<script>
function geoToggle(){
  var v = document.getElementById('geo_provider').value;
  function show(cls, on){
    document.querySelectorAll('.'+cls).forEach(el => { el.style.display = on ? 'block' : 'none'; });
  }
  show('ipapi-box',    v === 'ip-api');
  show('mm-local-box', v === 'maxmind_local');
  show('mm-web-box',   v === 'maxmind_web');
}
geoToggle();

function downloadMMDB(){
  const key = prompt('Enter your MaxMind license key (required to fetch GeoLite2 City):','<?= h($geo['mm_license_key'] ?? '') ?>');
  if (key === null) return;
  const form = document.createElement('form');
  form.method='post';
  form.innerHTML = `
    <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>
    <input type="hidden" name="action" value="download_mmdb"/>
    <input type="hidden" name="mm_license_key" value="${(key||'').replace(/"/g,'&quot;')}"/>
  `;
  document.body.appendChild(form);
  form.submit();
}

async function testGeo(){
  let ip = prompt('IP to test (e.g., 8.8.8.8):','');
  if (!ip) return;
  const out = document.getElementById('geo-test-output');
  out.style.display='block';
  out.textContent = 'Testing ' + ip + ' ...';
  try{
    const r = await fetch(<?= json_encode($API) ?> + 'api.geo.test&ip=' + encodeURIComponent(ip), {credentials:'same-origin', cache:'no-store'});
    const j = await r.json();
    out.textContent = JSON.stringify(j, null, 2);
  } catch(e){
    out.textContent = 'Error: ' + e;
  }
}

document.addEventListener('DOMContentLoaded', function(){
  var btn = document.getElementById('btn-mmdb-download');
  if (!btn) return;
  var out = document.getElementById('mmdb-download-out');
  btn.addEventListener('click', async function(){
    out.style.display='block';
    out.textContent = 'Starting download...';
    try{
      // Use license/path from current form values so user doesn't have to save first
      var license = document.querySelector('input[name="mm_license_key"]').value || '';
      var mmdbPath = document.querySelector('input[name="mmdb_path"]').value || '';
      const params = new URLSearchParams();
      if (license) params.set('license', license);
      if (mmdbPath) params.set('mmdb_path', mmdbPath);
      const r = await fetch(<?= json_encode($API) ?> + 'api.geo.download&' + params.toString(), {credentials:'same-origin', cache:'no-store'});
      const j = await r.json();
      out.textContent = JSON.stringify(j, null, 2);
    }catch(e){
      out.textContent = 'Error: ' + e;
    }
  });
});

</script>

<div class="card card--settings"><h3>Security &amp; Limits</h3>
  <form class="form" method="post" action="?p=admin.settings">
    <input type="hidden" name="section" value="security">
    <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>
    <div class="form-grid">
      <div class="form-label"><label for="rl_enabled"><strong>Enable rate limit</strong></label></div>
      <div class="form-ctl">
        <label><input id="rl_enabled" type="checkbox" name="rl_enabled" value="1" <?= !empty($cfg['rate_limit']['enabled']) ? 'checked' : '' ?>> Limit POSTs to track.php</label>
      </div>

      <div class="form-label"><label for="rl_window">Window (seconds)</label></div>
      <div class="form-ctl">
        <input id="rl_window" type="number" min="1" max="3600" step="1" name="rl_window" value="<?= (int)($cfg['rate_limit']['window_sec'] ?? 60) ?>"/>
      </div>

      <div class="form-label"><label for="rl_max">Max events / window</label></div>
      <div class="form-ctl">
        <input id="rl_max" type="number" min="1" max="100000" step="1" name="rl_max" value="<?= (int)($cfg['rate_limit']['max_events'] ?? 120) ?>"/>
      </div>

      <div class="form-label"><label for="ret_days">Retention (days)</label></div>
      <div class="form-ctl">
        <input id="ret_days" type="number" min="1" max="3650" step="1" name="ret_days" value="<?= (int)($cfg['retention']['days'] ?? 90) ?>"/>
        <div class="note" style="margin-top:6px">Used by <code>scripts/retention_purge.php</code>.</div>
      </div>
    </div>
    <div class="actions actions--left" style="margin-top:10px">
      <button class="btn btn--primary" type="submit">Save</button>
    </div>
  </form>
</div>

