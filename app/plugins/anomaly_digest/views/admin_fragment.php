<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<style>
  .ad-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .ad-admin .ad-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .ad-admin .ad-grid label {
    display: block;
    font-size: 13px;
  }
  .ad-admin input[type="number"] {
    width: 100%;
    box-sizing: border-box;
  }
  .ad-admin .ad-section + .ad-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .ad-admin .ad-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    align-items: center;
  }
  .ad-admin .ad-actions .button,
  .ad-admin .ad-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .ad-admin .ad-note,
  .ad-admin .ad-flash {
    font-size: 13px;
  }
  .ad-admin .ad-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .ad-admin .ad-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .ad-admin .ad-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .ad-admin .ad-stat strong {
    display: block;
    font-size: 22px;
  }
  .ad-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .ad-admin th,
  .ad-admin td {
    text-align: left;
    vertical-align: top;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
  }
  .ad-admin .sev-high { color: #b42318; font-weight: 700; }
  .ad-admin .sev-medium { color: #b54708; font-weight: 700; }
  .ad-admin .sev-low { color: #7a5a00; font-weight: 700; }
</style>
<div class="ad-admin" data-anomaly-digest-admin>
  <form data-anomaly-digest-form action="<?= $h(
      $this->service->routeUrl('anomaly_digest.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('anomaly_digest.reset')
) ?>" data-rebuild-url="<?= $h(
    $this->service->routeUrl('anomaly_digest.rebuild')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="ad-note">
      Anomaly Digest builds a small admin summary from existing plugin outputs and a few cheap traffic comparisons. It is designed to stay light and fast.
    </div>

    <section class="ad-section">
      <h5>Settings</h5>
      <div class="ad-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable anomaly digest</label>
        <label><input type="checkbox" name="include_traffic" value="1" <?= !empty($config['include_traffic']) ? 'checked' : '' ?>> Include traffic comparisons</label>
        <label><input type="checkbox" name="include_alerts" value="1" <?= !empty($config['include_alerts']) ? 'checked' : '' ?>> Include traffic alerts</label>
        <label><input type="checkbox" name="include_bot_watch" value="1" <?= !empty($config['include_bot_watch']) ? 'checked' : '' ?>> Include bot summary</label>
        <label><input type="checkbox" name="include_goals" value="1" <?= !empty($config['include_goals']) ? 'checked' : '' ?>> Include goal activity</label>
        <label><input type="checkbox" name="include_referrers" value="1" <?= !empty($config['include_referrers']) ? 'checked' : '' ?>> Include referrer summary</label>
        <label>Max digest items
          <input type="number" min="3" max="25" name="max_items" value="<?= $h($config['max_items'] ?? 10) ?>">
        </label>
      </div>
    </section>

    <section class="ad-section">
      <h5>Summary</h5>
      <div class="ad-stats">
        <div class="ad-stat"><span>High</span><strong><?= (int) ($report['summary']['high'] ?? 0) ?></strong></div>
        <div class="ad-stat"><span>Medium</span><strong><?= (int) ($report['summary']['medium'] ?? 0) ?></strong></div>
        <div class="ad-stat"><span>Low</span><strong><?= (int) ($report['summary']['low'] ?? 0) ?></strong></div>
      </div>

      <?php if (!empty($report['notes'])): ?>
        <?php foreach ($report['notes'] as $note): ?>
          <div class="ad-note"><?= $h($note) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section class="ad-section">
      <h5>Digest Items</h5>
      <table>
        <thead><tr><th>Source</th><th>Severity</th><th>Title</th><th>Detail</th></tr></thead>
        <tbody>
          <?php if (!empty($report['items'])): ?>
            <?php foreach ($report['items'] as $item): ?>
              <?php $sev = (string) ($item['severity'] ?? 'low'); ?>
              <tr>
                <td><?= $h((string) ($item['source'] ?? '')) ?></td>
                <td class="sev-<?= $h($sev) ?>"><?= $h(ucfirst($sev)) ?></td>
                <td><?= $h((string) ($item['title'] ?? '')) ?></td>
                <td><?= $h((string) ($item['detail'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4">No digest items yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="ad-actions">
      <button type="submit" class="button btn disable">Save Anomaly Digest Settings</button>
      <button type="button" class="button" data-anomaly-digest-rebuild>Refresh Digest</button>
      <button type="button" class="button" data-anomaly-digest-reset>Reset To Defaults</button>
    </div>
    <div class="ad-flash" data-anomaly-digest-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-anomaly-digest-admin')) {
    root = document.querySelector('[data-anomaly-digest-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-anomaly-digest-form]');
  var flash = root.querySelector('[data-anomaly-digest-flash]');
  var resetBtn = root.querySelector('[data-anomaly-digest-reset]');
  var rebuildBtn = root.querySelector('[data-anomaly-digest-rebuild]');

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
      if (!confirm('Reset Anomaly Digest to defaults?')) return;
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
      setFlash('Refreshing digest...', false);
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
