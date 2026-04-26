<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<style>
  .pa-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .pa-admin .pa-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .pa-admin .pa-grid label {
    display: block;
    font-size: 13px;
  }
  .pa-admin .pa-section + .pa-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .pa-admin .pa-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    align-items: center;
  }
  .pa-admin .pa-actions .button,
  .pa-admin .pa-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .pa-admin .pa-note,
  .pa-admin .pa-flash {
    font-size: 13px;
  }
  .pa-admin .pa-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .pa-admin .pa-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .pa-admin .pa-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .pa-admin .pa-stat strong {
    display: block;
    font-size: 22px;
  }
  .pa-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .pa-admin th,
  .pa-admin td {
    text-align: left;
    vertical-align: top;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
  }
  .pa-admin .sev-high { color: #b42318; font-weight: 700; }
  .pa-admin .sev-medium { color: #b54708; font-weight: 700; }
  .pa-admin .sev-low { color: #7a5a00; font-weight: 700; }
  .pa-admin .sev-pass { color: #027a48; font-weight: 700; }
</style>
<div class="pa-admin" data-privacy-audit-admin>
  <form data-privacy-audit-form action="<?= $h(
      $this->service->routeUrl('privacy_audit.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('privacy_audit.reset')
) ?>" data-rebuild-url="<?= $h(
    $this->service->routeUrl('privacy_audit.rebuild')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="pa-note">
      Privacy Audit checks current app and plugin settings for privacy-sensitive patterns. It is lightweight and config-driven, not a substitute for a full manual review.
    </div>

    <section class="pa-section">
      <h5>Settings</h5>
      <div class="pa-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable privacy audit</label>
        <label><input type="checkbox" name="strict_mode" value="1" <?= !empty($config['strict_mode']) ? 'checked' : '' ?>> Strict mode</label>
        <label><input type="checkbox" name="scan_plugin_settings" value="1" <?= !empty($config['scan_plugin_settings']) ? 'checked' : '' ?>> Scan plugin settings</label>
        <label><input type="checkbox" name="include_passes" value="1" <?= !empty($config['include_passes']) ? 'checked' : '' ?>> Show passing checks</label>
      </div>
    </section>

    <section class="pa-section">
      <h5>Summary</h5>
      <div class="pa-stats">
        <div class="pa-stat"><span>High</span><strong><?= (int) ($report['summary']['high'] ?? 0) ?></strong></div>
        <div class="pa-stat"><span>Medium</span><strong><?= (int) ($report['summary']['medium'] ?? 0) ?></strong></div>
        <div class="pa-stat"><span>Low</span><strong><?= (int) ($report['summary']['low'] ?? 0) ?></strong></div>
        <div class="pa-stat"><span>Pass</span><strong><?= (int) ($report['summary']['pass'] ?? 0) ?></strong></div>
      </div>

      <?php if (!empty($report['notes'])): ?>
        <?php foreach ($report['notes'] as $note): ?>
          <div class="pa-note"><?= $h($note) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="pa-section">
      <h5>Findings</h5>
      <table>
        <thead><tr><th>Area</th><th>Severity</th><th>Finding</th><th>Recommendation</th></tr></thead>
        <tbody>
          <?php if (!empty($report['findings'])): ?>
            <?php foreach ($report['findings'] as $finding): ?>
              <?php $sev = (string) ($finding['severity'] ?? 'low'); ?>
              <tr>
                <td><?= $h((string) ($finding['area'] ?? '')) ?></td>
                <td class="sev-<?= $h($sev) ?>"><?= $h(ucfirst($sev)) ?></td>
                <td><?= $h((string) ($finding['title'] ?? '')) ?></td>
                <td><?= $h((string) ($finding['recommendation'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4">No findings.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="pa-actions">
      <button type="submit" class="button btn disable">Save Privacy Audit Settings</button>
      <button type="button" class="button" data-privacy-audit-rebuild>Refresh Audit</button>
      <button type="button" class="button" data-privacy-audit-reset>Reset To Defaults</button>
    </div>
    <div class="pa-flash" data-privacy-audit-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-privacy-audit-admin')) {
    root = document.querySelector('[data-privacy-audit-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-privacy-audit-form]');
  var flash = root.querySelector('[data-privacy-audit-flash]');
  var resetBtn = root.querySelector('[data-privacy-audit-reset]');
  var rebuildBtn = root.querySelector('[data-privacy-audit-rebuild]');

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

  if (form) {
    stopDragPropagation(form);
    form.addEventListener('submit', function(e){
      e.preventDefault();
      setFlash('Saving...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('action') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash((data && data.error) ? data.error : 'Save failed.', true);
          return;
        }
        setFlash('Settings saved.', false);
        window.location.reload();
      };
      xhr.send(encodeFormData(form));
    });
  }

  if (resetBtn && form) {
    stopDragPropagation(resetBtn);
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Privacy Audit to defaults?')) return;
      setFlash('Resetting...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-reset-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash('Reset failed.', true);
          return;
        }
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }

  if (rebuildBtn && form) {
    stopDragPropagation(rebuildBtn);
    rebuildBtn.addEventListener('click', function(){
      setFlash('Refreshing audit...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-rebuild-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash('Refresh failed.', true);
          return;
        }
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }
})();
</script>
