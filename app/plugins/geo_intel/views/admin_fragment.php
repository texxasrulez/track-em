<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<style>
  .gi-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .gi-admin .gi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .gi-admin .gi-grid label {
    display: block;
    font-size: 13px;
  }
  .gi-admin input[type="number"],
  .gi-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .gi-admin .gi-section + .gi-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .gi-admin .gi-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    align-items: center;
  }
  .gi-admin .gi-actions .button,
  .gi-admin .gi-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .gi-admin .gi-note,
  .gi-admin .gi-flash {
    font-size: 13px;
  }
  .gi-admin .gi-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .gi-admin .gi-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .gi-admin .gi-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .gi-admin .gi-stat strong {
    display: block;
    font-size: 22px;
  }
  .gi-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .gi-admin th,
  .gi-admin td {
    text-align: left;
    vertical-align: top;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
  }
</style>
<div class="gi-admin" data-geo-intel-admin>
  <form data-geo-intel-form action="<?= $h(
      $this->service->routeUrl('geo_intel.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('geo_intel.reset')
) ?>" data-rebuild-url="<?= $h(
    $this->service->routeUrl('geo_intel.rebuild')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="gi-note">
      Geo Intel summarizes existing geo-enriched visits into aggregate country reporting, with optional city summaries for admin review.
    </div>

    <section class="gi-section">
      <h5>Settings</h5>
      <div class="gi-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable geo intel</label>
        <label>Report range
          <select name="report_range">
            <option value="today" <?= ($config['report_range'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="7d" <?= ($config['report_range'] ?? '') === '7d' ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30d" <?= ($config['report_range'] ?? '30d') === '30d' ? 'selected' : '' ?>>Last 30 days</option>
            <option value="all" <?= ($config['report_range'] ?? '') === 'all' ? 'selected' : '' ?>>All time</option>
          </select>
        </label>
        <label>Max rows
          <input type="number" name="max_rows" min="5" max="100" value="<?= $h($config['max_rows'] ?? 20) ?>">
        </label>
        <label><input type="checkbox" name="include_city_summary" value="1" <?= !empty($config['include_city_summary']) ? 'checked' : '' ?>> Include city summary</label>
        <label><input type="checkbox" name="group_unknown" value="1" <?= !empty($config['group_unknown']) ? 'checked' : '' ?>> Group unknown geo values</label>
      </div>
    </section>

    <section class="gi-section">
      <h5>Reporting</h5>
      <div class="gi-stats">
        <div class="gi-stat"><span>Visits Scanned</span><strong><?= (int) ($report['summary']['visits_scanned'] ?? 0) ?></strong></div>
        <div class="gi-stat"><span>Geo Visits</span><strong><?= (int) ($report['summary']['geo_visits'] ?? 0) ?></strong></div>
        <div class="gi-stat"><span>Countries</span><strong><?= (int) ($report['summary']['countries'] ?? 0) ?></strong></div>
        <div class="gi-stat"><span>Cities</span><strong><?= (int) ($report['summary']['cities'] ?? 0) ?></strong></div>
      </div>

      <?php if (!empty($report['notes'])): ?>
        <?php foreach ($report['notes'] as $note): ?>
          <div class="gi-note"><?= $h($note) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <h6>Top Countries</h6>
      <table>
        <thead><tr><th>Country</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_countries'])): ?>
            <?php foreach ($report['top_countries'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No country data available in this range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if (!empty($config['include_city_summary'])): ?>
        <h6 style="margin-top:12px">Top Cities</h6>
        <table>
          <thead><tr><th>City</th><th>Count</th></tr></thead>
          <tbody>
            <?php if (!empty($report['top_cities'])): ?>
              <?php foreach ($report['top_cities'] as $row): ?>
                <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="2">No city data available in this range.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <h6 style="margin-top:12px">Trend By Day</h6>
      <table>
        <thead><tr><th>Day</th><th>Geo Visits</th></tr></thead>
        <tbody>
          <?php if (!empty($report['trend'])): ?>
            <?php foreach ($report['trend'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No trend data available.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="gi-actions">
      <button type="submit" class="button btn disable">Save Geo Intel Settings</button>
      <button type="button" class="button" data-geo-intel-rebuild>Refresh / Rebuild Report</button>
      <button type="button" class="button" data-geo-intel-reset>Reset To Defaults</button>
    </div>
    <div class="gi-flash" data-geo-intel-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-geo-intel-admin')) {
    root = document.querySelector('[data-geo-intel-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-geo-intel-form]');
  var flash = root.querySelector('[data-geo-intel-flash]');
  var resetBtn = root.querySelector('[data-geo-intel-reset]');
  var rebuildBtn = root.querySelector('[data-geo-intel-rebuild]');

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
      if (!confirm('Reset Geo Intel to defaults?')) return;
      setFlash('Resetting...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-reset-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash((data && data.error) ? data.error : 'Reset failed.', true);
          return;
        }
        setFlash('Defaults restored.', false);
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }

  if (rebuildBtn && form) {
    stopDragPropagation(rebuildBtn);
    rebuildBtn.addEventListener('click', function(){
      setFlash('Rebuilding...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-rebuild-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash((data && data.error) ? data.error : 'Rebuild failed.', true);
          return;
        }
        setFlash('Report refreshed.', false);
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }
})();
</script>
