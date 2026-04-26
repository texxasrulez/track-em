<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$searchDomains = implode("\n", $config['search_domains'] ?? []);
$socialDomains = implode("\n", $config['social_domains'] ?? []);
$internalDomains = implode("\n", $config['internal_domains'] ?? []);
?>
<style>
  .ri-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .ri-admin .ri-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .ri-admin .ri-grid label {
    display: block;
    font-size: 13px;
  }
  .ri-admin textarea {
    width: 100%;
    min-height: 92px;
    resize: vertical;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    background: var(--muted);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px;
    box-sizing: border-box;
  }
  .ri-admin input[type="text"],
  .ri-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .ri-admin .ri-section + .ri-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .ri-admin .ri-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 12px;
  }
  .ri-admin .ri-actions .button,
  .ri-admin .ri-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .ri-admin .ri-report-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .ri-admin .ri-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .ri-admin .ri-stat strong {
    display: block;
    font-size: 22px;
  }
  .ri-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }
  .ri-admin th,
  .ri-admin td {
    text-align: left;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: top;
  }
  .ri-admin .ri-note,
  .ri-admin .ri-flash {
    font-size: 13px;
  }
  .ri-admin .ri-flash {
    min-height: 18px;
    margin-top: 10px;
  }
</style>
<div class="ri-admin" data-referrer-intel-admin>
  <form data-referrer-intel-form action="<?= $h(
      $this->service->routeUrl('referrer_intel.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('referrer_intel.reset')
) ?>" data-rebuild-url="<?= $h(
    $this->service->routeUrl('referrer_intel.rebuild')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="ri-note">
      Referrer Intel converts raw referrers into domain-level source reporting. Full URLs are hidden by default and query strings are stripped unless explicitly enabled.
    </div>

    <section class="ri-section">
      <h5>Settings</h5>
      <div class="ri-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty(
            $config['enabled']
        )
            ? 'checked'
            : '' ?>> Enable referrer reporting</label>
        <label>Report range
          <select name="report_range">
            <option value="today" <?= ($config['report_range'] ?? '') === 'today'
                ? 'selected'
                : '' ?>>Today</option>
            <option value="7d" <?= ($config['report_range'] ?? '') === '7d'
                ? 'selected'
                : '' ?>>Last 7 days</option>
            <option value="30d" <?= ($config['report_range'] ?? '30d') === '30d'
                ? 'selected'
                : '' ?>>Last 30 days</option>
            <option value="all" <?= ($config['report_range'] ?? '') === 'all'
                ? 'selected'
                : '' ?>>All time</option>
          </select>
        </label>
        <label><input type="checkbox" name="show_referrer_paths" value="1" <?= !empty(
            $config['show_referrer_paths']
        )
            ? 'checked'
            : '' ?>> Show sanitized referrer paths</label>
        <label><input type="checkbox" name="include_query_strings" value="1" <?= !empty(
            $config['include_query_strings']
        )
            ? 'checked'
            : '' ?>> Include query strings in path summaries</label>
      </div>

      <div class="ri-grid">
        <label>Search domains
          <textarea name="search_domains"><?= $h($searchDomains) ?></textarea>
        </label>
        <label>Social domains
          <textarea name="social_domains"><?= $h($socialDomains) ?></textarea>
        </label>
        <label>Internal domains
          <textarea name="internal_domains" placeholder="Optional extra internal domains"><?= $h(
              $internalDomains
          ) ?></textarea>
        </label>
      </div>
    </section>

    <section class="ri-section">
      <h5>Summary</h5>
      <div class="ri-report-grid">
        <?php foreach ($report['summary'] as $label => $count): ?>
          <div class="ri-stat">
            <span><?= $h(ucfirst(str_replace('_', ' ', $label))) ?></span>
            <strong><?= (int) $count ?></strong>
          </div>
        <?php endforeach; ?>
      </div>

      <h6>Traffic Trends</h6>
      <table>
        <thead>
          <tr>
            <th>Window</th>
            <th>Direct</th>
            <th>Search</th>
            <th>Social</th>
            <th>Internal</th>
            <th>External</th>
            <th>Unknown</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (['today' => 'Today', '7d' => 'Last 7 days', '30d' => 'Last 30 days'] as $key => $label): ?>
            <?php $bucket = $report['windows'][$key] ?? []; ?>
            <tr>
              <td><?= $h($label) ?></td>
              <td><?= (int) ($bucket['direct'] ?? 0) ?></td>
              <td><?= (int) ($bucket['search'] ?? 0) ?></td>
              <td><?= (int) ($bucket['social'] ?? 0) ?></td>
              <td><?= (int) ($bucket['internal'] ?? 0) ?></td>
              <td><?= (int) ($bucket['external'] ?? 0) ?></td>
              <td><?= (int) ($bucket['unknown'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Top Referring Domains</h6>
      <table>
        <thead><tr><th>Domain</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_domains'])): ?>
            <?php foreach ($report['top_domains'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No referring domains in this range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if (!empty($report['top_paths'])): ?>
        <h6 style="margin-top:12px">Top Referrer Paths</h6>
        <table>
          <thead><tr><th>Referrer</th><th>Count</th></tr></thead>
          <tbody>
            <?php foreach ($report['top_paths'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <?php if (!empty($report['notes'])): ?>
        <?php foreach ($report['notes'] as $note): ?>
          <div class="ri-note" style="margin-top:10px"><?= $h($note) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <div class="ri-actions">
      <button type="submit" class="button btn disable">Save Referrer Intel Settings</button>
      <button type="button" class="button" data-referrer-rebuild>Refresh / Rebuild Report</button>
      <button type="button" class="button" data-referrer-reset>Reset To Defaults</button>
    </div>
    <div class="ri-flash" data-referrer-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-referrer-intel-admin')) {
    root = document.querySelector('[data-referrer-intel-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-referrer-intel-form]');
  var flash = root.querySelector('[data-referrer-flash]');
  var resetBtn = root.querySelector('[data-referrer-reset]');
  var rebuildBtn = root.querySelector('[data-referrer-rebuild]');

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
        setFlash('Settings saved. Reload the Plugins page to refresh the report.', false);
      };
      xhr.send(encodeFormData(form));
    });
  }

  if (resetBtn && form) {
    stopDragPropagation(resetBtn);
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Referrer Intel to defaults?')) return;
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
      setFlash('Rebuilding...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-rebuild-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash('Rebuild failed.', true);
          return;
        }
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }
})();
</script>
