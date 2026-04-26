<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<style>
  .ph-admin { border: 1px solid var(--border); border-radius: 12px; padding: 14px; background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent); }
  .ph-admin .ph-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px 12px; margin: 10px 0 12px; }
  .ph-admin .ph-grid label { display: block; font-size: 13px; }
  .ph-admin input[type="number"] { width: 100%; box-sizing: border-box; }
  .ph-admin .ph-section + .ph-section { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border); }
  .ph-admin .ph-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; align-items: center; }
  .ph-admin .ph-actions .button, .ph-admin .ph-actions button { width: auto; flex: 0 0 auto; }
  .ph-admin .ph-note, .ph-admin .ph-flash { font-size: 13px; }
  .ph-admin .ph-flash { min-height: 18px; margin-top: 10px; }
  .ph-admin .ph-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 10px; margin: 10px 0 14px; }
  .ph-admin .ph-stat { padding: 12px; border: 1px solid var(--border); border-radius: 10px; background: rgba(255,255,255,0.45); }
  .ph-admin .ph-stat strong { display: block; font-size: 22px; }
  .ph-admin table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  .ph-admin th, .ph-admin td { text-align: left; vertical-align: top; padding: 6px 8px; border-bottom: 1px solid var(--border); font-size: 13px; }
  .ph-admin .sev-high { color: #b42318; font-weight: 700; }
  .ph-admin .sev-medium { color: #b54708; font-weight: 700; }
  .ph-admin .sev-low { color: #7a5a00; font-weight: 700; }
  .ph-admin .sev-pass { color: #027a48; font-weight: 700; }
</style>
<div class="ph-admin" data-plugin-health-admin>
  <form data-plugin-health-form action="<?= $h($this->service->routeUrl('plugin_health.save')) ?>" data-reset-url="<?= $h($this->service->routeUrl('plugin_health.reset')) ?>" data-rebuild-url="<?= $h($this->service->routeUrl('plugin_health.rebuild')) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="ph-note">
      Plugin Health inspects plugin manifests, storage, simple dependencies, stale caches, and recent plugin-side error markers. It is lightweight and admin-only.
    </div>

    <section class="ph-section">
      <h5>Settings</h5>
      <div class="ph-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable plugin health</label>
        <label><input type="checkbox" name="strict_mode" value="1" <?= !empty($config['strict_mode']) ? 'checked' : '' ?>> Strict mode</label>
        <label><input type="checkbox" name="include_passes" value="1" <?= !empty($config['include_passes']) ? 'checked' : '' ?>> Show passing checks</label>
        <label><input type="checkbox" name="scan_disabled_plugins" value="1" <?= !empty($config['scan_disabled_plugins']) ? 'checked' : '' ?>> Scan disabled plugins too</label>
        <label>Stale cache threshold (hours)
          <input type="number" name="stale_cache_hours" min="1" max="720" value="<?= $h($config['stale_cache_hours'] ?? 24) ?>">
        </label>
      </div>
    </section>

    <section class="ph-section">
      <h5>Summary</h5>
      <div class="ph-stats">
        <div class="ph-stat"><span>High</span><strong><?= (int) ($report['summary']['high'] ?? 0) ?></strong></div>
        <div class="ph-stat"><span>Medium</span><strong><?= (int) ($report['summary']['medium'] ?? 0) ?></strong></div>
        <div class="ph-stat"><span>Low</span><strong><?= (int) ($report['summary']['low'] ?? 0) ?></strong></div>
        <div class="ph-stat"><span>Pass</span><strong><?= (int) ($report['summary']['pass'] ?? 0) ?></strong></div>
      </div>
      <?php if (!empty($report['notes'])): foreach ($report['notes'] as $note): ?>
        <div class="ph-note"><?= $h($note) ?></div>
      <?php endforeach; endif; ?>
    </section>

    <section class="ph-section">
      <h5>Plugins</h5>
      <table>
        <thead><tr><th>Plugin</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (!empty($report['plugins'])): foreach ($report['plugins'] as $plugin): ?>
            <tr>
              <td><?= $h((string) ($plugin['name'] ?? $plugin['id'] ?? '')) ?> <span class="ph-note">(<?= $h((string) ($plugin['id'] ?? '')) ?>)</span></td>
              <td><?= !empty($plugin['enabled']) ? 'Enabled' : 'Disabled' ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="2">No plugin rows available.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <section class="ph-section">
      <h5>Findings</h5>
      <table>
        <thead><tr><th>Plugin</th><th>Severity</th><th>Finding</th><th>Recommendation</th></tr></thead>
        <tbody>
          <?php if (!empty($report['findings'])): foreach ($report['findings'] as $finding): $sev = (string) ($finding['severity'] ?? 'low'); ?>
            <tr>
              <td><?= $h((string) ($finding['plugin'] ?? '')) ?></td>
              <td class="sev-<?= $h($sev) ?>"><?= $h(ucfirst($sev)) ?></td>
              <td><?= $h((string) ($finding['title'] ?? '')) ?></td>
              <td><?= $h((string) ($finding['recommendation'] ?? '')) ?></td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="4">No findings.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="ph-actions">
      <button type="submit" class="button btn disable">Save Plugin Health Settings</button>
      <button type="button" class="button" data-plugin-health-rebuild>Refresh Health Check</button>
      <button type="button" class="button" data-plugin-health-reset>Reset To Defaults</button>
    </div>
    <div class="ph-flash" data-plugin-health-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-plugin-health-admin')) {
    root = document.querySelector('[data-plugin-health-admin]:last-of-type');
  }
  if (!root) return;
  var form = root.querySelector('[data-plugin-health-form]');
  var flash = root.querySelector('[data-plugin-health-flash]');
  var resetBtn = root.querySelector('[data-plugin-health-reset]');
  var rebuildBtn = root.querySelector('[data-plugin-health-rebuild]');
  function setFlash(msg, isError){
    if (!flash) return;
    flash.textContent = msg || '';
    flash.style.color = isError ? '#c2410c' : 'var(--text)';
  }
  function stopDragPropagation(el){
    if (!el) return;
    ['mousedown', 'pointerdown', 'dragstart', 'touchstart'].forEach(function(type){
      el.addEventListener(type, function(e){ e.stopPropagation(); });
    });
  }
  function encodeFormData(formEl){
    var pairs = [];
    var els = formEl.querySelectorAll('[name]');
    for (var i = 0; i < els.length; i++) {
      var el = els[i];
      if (!el.name) continue;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
      pairs.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.type === 'checkbox' ? '1' : el.value));
    }
    return pairs.join('&');
  }
  function post(url, body, okMsg){
    var xhr = new XMLHttpRequest();
    xhr.open('POST', url || '', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function(){
      if (xhr.readyState !== 4) return;
      var data = null;
      try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
      if (xhr.status !== 200 || !data || data.ok !== true) {
        setFlash((data && data.error) ? data.error : 'Request failed.', true);
        return;
      }
      setFlash(okMsg, false);
      window.location.reload();
    };
    xhr.send(body);
  }
  if (form) {
    stopDragPropagation(form);
    form.addEventListener('submit', function(e){
      e.preventDefault();
      setFlash('Saving...', false);
      post(form.getAttribute('action') || '', encodeFormData(form), 'Settings saved.');
    });
  }
  if (resetBtn && form) {
    stopDragPropagation(resetBtn);
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Plugin Health to defaults?')) return;
      post(form.getAttribute('data-reset-url') || '', 'csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''), 'Defaults restored.');
    });
  }
  if (rebuildBtn && form) {
    stopDragPropagation(rebuildBtn);
    rebuildBtn.addEventListener('click', function(){
      post(form.getAttribute('data-rebuild-url') || '', 'csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''), 'Health check refreshed.');
    });
  }
})();
</script>
