<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<style>
  .ui-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .ui-admin .ui-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .ui-admin .ui-grid label {
    display: block;
    font-size: 13px;
  }
  .ui-admin input[type="text"],
  .ui-admin input[type="number"],
  .ui-admin textarea,
  .ui-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .ui-admin textarea {
    min-height: 84px;
    resize: vertical;
  }
  .ui-admin .ui-section + .ui-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .ui-admin .ui-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    align-items: center;
  }
  .ui-admin .ui-actions .button,
  .ui-admin .ui-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .ui-admin .ui-note,
  .ui-admin .ui-flash {
    font-size: 13px;
  }
  .ui-admin .ui-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .ui-admin .ui-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .ui-admin .ui-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .ui-admin .ui-stat strong {
    display: block;
    font-size: 22px;
  }
  .ui-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .ui-admin th,
  .ui-admin td {
    text-align: left;
    vertical-align: top;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
  }
</style>
<div class="ui-admin" data-utm-intel-admin>
  <form data-utm-intel-form action="<?= $h(
      $this->service->routeUrl('utm_intel.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('utm_intel.reset')
) ?>" data-rebuild-url="<?= $h(
    $this->service->routeUrl('utm_intel.rebuild')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="ui-note">
      UTM Intel summarizes lightweight source and campaign parameters from tracked paths. It is intended for simple campaign visibility, not full attribution or user profiling.
    </div>

    <section class="ui-section">
      <h5>Settings</h5>
      <div class="ui-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable UTM Intel</label>
        <label>Report range
          <select name="report_range">
            <option value="today" <?= ($config['report_range'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="7d" <?= ($config['report_range'] ?? '') === '7d' ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30d" <?= ($config['report_range'] ?? '30d') === '30d' ? 'selected' : '' ?>>Last 30 days</option>
            <option value="all" <?= ($config['report_range'] ?? '') === 'all' ? 'selected' : '' ?>>All time</option>
          </select>
        </label>
        <label>Source parameter
          <input type="text" name="source_param" maxlength="40" value="<?= $h($config['source_param'] ?? 'utm_source') ?>">
        </label>
        <label>Medium parameter
          <input type="text" name="medium_param" maxlength="40" value="<?= $h($config['medium_param'] ?? 'utm_medium') ?>">
        </label>
        <label>Campaign parameter
          <input type="text" name="campaign_param" maxlength="40" value="<?= $h($config['campaign_param'] ?? 'utm_campaign') ?>">
        </label>
        <label>Content parameter
          <input type="text" name="content_param" maxlength="40" value="<?= $h($config['content_param'] ?? 'utm_content') ?>">
        </label>
        <label>Term parameter
          <input type="text" name="term_param" maxlength="40" value="<?= $h($config['term_param'] ?? 'utm_term') ?>">
        </label>
        <label>Max rows per table
          <input type="number" name="max_rows" min="5" max="100" value="<?= $h($config['max_rows'] ?? 20) ?>">
        </label>
      </div>
      <div class="ui-grid">
        <label>Excluded sources
          <textarea name="exclude_sources" spellcheck="false"><?= $h(implode("\n", (array) ($config['exclude_sources'] ?? []))) ?></textarea>
        </label>
        <label>Excluded mediums
          <textarea name="exclude_mediums" spellcheck="false"><?= $h(implode("\n", (array) ($config['exclude_mediums'] ?? []))) ?></textarea>
        </label>
      </div>
    </section>

    <section class="ui-section">
      <h5>Reporting</h5>
      <div class="ui-stats">
        <div class="ui-stat"><span>Visits Scanned</span><strong><?= (int) ($report['summary']['visits_scanned'] ?? 0) ?></strong></div>
        <div class="ui-stat"><span>UTM Visits</span><strong><?= (int) ($report['summary']['utm_visits'] ?? 0) ?></strong></div>
        <div class="ui-stat"><span>Campaigns</span><strong><?= (int) ($report['summary']['campaigns'] ?? 0) ?></strong></div>
      </div>

      <?php if (!empty($report['notes'])): ?>
        <?php foreach ($report['notes'] as $note): ?>
          <div class="ui-note"><?= $h($note) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <h6>Top Sources</h6>
      <table>
        <thead><tr><th>Source</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_sources'])): ?>
            <?php foreach ($report['top_sources'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No UTM sources found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Top Mediums</h6>
      <table>
        <thead><tr><th>Medium</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_mediums'])): ?>
            <?php foreach ($report['top_mediums'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No UTM mediums found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Top Campaigns</h6>
      <table>
        <thead><tr><th>Campaign</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_campaigns'])): ?>
            <?php foreach ($report['top_campaigns'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No UTM campaigns found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Top Source / Medium Pairs</h6>
      <table>
        <thead><tr><th>Pair</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_source_mediums'])): ?>
            <?php foreach ($report['top_source_mediums'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No source / medium pairs found.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Recent Examples</h6>
      <table>
        <thead><tr><th>Path</th><th>Source</th><th>Medium</th><th>Campaign</th></tr></thead>
        <tbody>
          <?php if (!empty($report['recent_examples'])): ?>
            <?php foreach ($report['recent_examples'] as $row): ?>
              <tr>
                <td><?= $h($row['path'] ?? '') ?></td>
                <td><?= $h($row['source'] ?? '') ?></td>
                <td><?= $h($row['medium'] ?? '') ?></td>
                <td><?= $h($row['campaign'] ?? '') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4">No recent UTM-tagged examples available.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="ui-actions">
      <button type="submit" class="button btn disable">Save UTM Intel Settings</button>
      <button type="button" class="button" data-utm-intel-rebuild>Refresh / Rebuild Report</button>
      <button type="button" class="button" data-utm-intel-reset>Reset To Defaults</button>
    </div>
    <div class="ui-flash" data-utm-intel-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-utm-intel-admin')) {
    root = document.querySelector('[data-utm-intel-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-utm-intel-form]');
  var flash = root.querySelector('[data-utm-intel-flash]');
  var resetBtn = root.querySelector('[data-utm-intel-reset]');
  var rebuildBtn = root.querySelector('[data-utm-intel-rebuild]');

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
      if (!confirm('Reset UTM Intel to defaults?')) return;
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
