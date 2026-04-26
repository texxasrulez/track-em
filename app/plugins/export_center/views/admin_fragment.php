<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$sections = is_array($config['include_sections'] ?? null) ? $config['include_sections'] : [];
?>
<style>
  .ec-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .ec-admin .ec-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .ec-admin .ec-grid label {
    display: block;
    font-size: 13px;
  }
  .ec-admin input[type="number"],
  .ec-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .ec-admin .ec-section + .ec-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .ec-admin .ec-note,
  .ec-admin .ec-flash {
    font-size: 13px;
  }
  .ec-admin .ec-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .ec-admin .ec-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 12px;
  }
  .ec-admin .ec-actions .button,
  .ec-admin .ec-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .ec-admin .ec-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
  }
  .ec-admin .ec-badge {
    display: inline-block;
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 3px 9px;
    font-size: 12px;
  }
  .ec-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }
  .ec-admin th,
  .ec-admin td {
    text-align: left;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: top;
  }
</style>
<div class="ec-admin" data-export-center-admin>
  <form
    data-export-center-form
    action="<?= $h($this->service->routeUrl('export_center.save')) ?>"
    data-reset-url="<?= $h($this->service->routeUrl('export_center.reset')) ?>"
    data-generate-url="<?= $h($this->service->routeUrl('export_center.generate')) ?>"
  >
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="ec-note">
      Export Center creates cached admin-only JSON and CSV snapshots from existing reporting data. It is intended for lightweight exports, not raw bulk data dumps.
    </div>

    <section class="ec-section">
      <h5>Settings</h5>
      <div class="ec-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable export center</label>
        <label>Report range
          <select name="report_range">
            <option value="today" <?= ($config['report_range'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="7d" <?= ($config['report_range'] ?? '') === '7d' ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30d" <?= ($config['report_range'] ?? '30d') === '30d' ? 'selected' : '' ?>>Last 30 days</option>
            <option value="all" <?= ($config['report_range'] ?? '') === 'all' ? 'selected' : '' ?>>All time</option>
          </select>
        </label>
        <label>Export format
          <select name="export_format">
            <option value="json" <?= ($config['export_format'] ?? '') === 'json' ? 'selected' : '' ?>>JSON only</option>
            <option value="csv" <?= ($config['export_format'] ?? '') === 'csv' ? 'selected' : '' ?>>CSV only</option>
            <option value="both" <?= ($config['export_format'] ?? 'both') === 'both' ? 'selected' : '' ?>>JSON and CSV</option>
          </select>
        </label>
        <label>Retention count
          <input type="number" min="1" max="200" name="retention_count" value="<?= $h($config['retention_count'] ?? 30) ?>">
        </label>
      </div>
    </section>

    <section class="ec-section">
      <h5>Included Sections</h5>
      <div class="ec-grid">
        <label><input type="checkbox" name="include_traffic_summary" value="1" <?= !empty($sections['traffic_summary']) ? 'checked' : '' ?>> Traffic summary</label>
        <label><input type="checkbox" name="include_top_paths" value="1" <?= !empty($sections['top_paths']) ? 'checked' : '' ?>> Top paths</label>
        <label><input type="checkbox" name="include_referrer_summary" value="1" <?= !empty($sections['referrer_summary']) ? 'checked' : '' ?>> Referrer summary</label>
        <label><input type="checkbox" name="include_event_summary" value="1" <?= !empty($sections['event_summary']) ? 'checked' : '' ?>> Event summary</label>
        <label><input type="checkbox" name="include_goals_summary" value="1" <?= !empty($sections['goals_summary']) ? 'checked' : '' ?>> Goals summary</label>
      </div>
      <div class="ec-badges">
        <span class="ec-badge">Referrer Intel: <?= !empty($capabilities['referrer_intel']) ? 'available' : 'missing' ?></span>
        <span class="ec-badge">Event Tracking: <?= !empty($capabilities['event_tracking']) ? 'available' : 'missing' ?></span>
        <span class="ec-badge">Goals: <?= !empty($capabilities['goals']) ? 'available' : 'missing' ?></span>
      </div>
    </section>

    <section class="ec-section">
      <h5>Stored Exports</h5>
      <table>
        <thead><tr><th>File</th><th>Type</th><th>Generated</th><th>Size</th><th>Action</th></tr></thead>
        <tbody>
          <?php if ($exports): ?>
            <?php foreach ($exports as $export): ?>
              <tr>
                <td><?= $h((string) ($export['file'] ?? '')) ?></td>
                <td><?= $h(strtoupper((string) ($export['type'] ?? ''))) ?></td>
                <td><?= !empty($export['generated_at']) ? $h(date('Y-m-d H:i:s', (int) $export['generated_at'])) : 'Unknown' ?></td>
                <td><?= $h((string) round(((int) ($export['size_bytes'] ?? 0)) / 1024, 1)) ?> KB</td>
                <td><a class="button" href="<?= $h((string) ($export['download_url'] ?? '#')) ?>">Download</a></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5">No exports generated yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="ec-actions">
      <button type="submit" class="button btn disable">Save Export Center Settings</button>
      <button type="button" class="button" data-export-center-generate>Generate Export</button>
      <button type="button" class="button" data-export-center-reset>Reset To Defaults</button>
    </div>
    <div class="ec-flash" data-export-center-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-export-center-admin')) {
    root = document.querySelector('[data-export-center-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-export-center-form]');
  var flash = root.querySelector('[data-export-center-flash]');
  var resetBtn = root.querySelector('[data-export-center-reset]');
  var generateBtn = root.querySelector('[data-export-center-generate]');

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
      };
      xhr.send(encodeFormData(form));
    });
  }

  if (resetBtn && form) {
    stopDragPropagation(resetBtn);
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Export Center to defaults?')) return;
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

  if (generateBtn && form) {
    stopDragPropagation(generateBtn);
    generateBtn.addEventListener('click', function(){
      setFlash('Generating export...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-generate-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash('Generate failed.', true);
          return;
        }
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }
})();
</script>
