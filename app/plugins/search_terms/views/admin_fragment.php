<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<style>
  .st-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .st-admin .st-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .st-admin .st-grid label {
    display: block;
    font-size: 13px;
  }
  .st-admin input[type="text"],
  .st-admin input[type="number"],
  .st-admin textarea,
  .st-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .st-admin textarea {
    min-height: 84px;
    resize: vertical;
  }
  .st-admin .st-section + .st-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .st-admin .st-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    align-items: center;
  }
  .st-admin .st-actions .button,
  .st-admin .st-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .st-admin .st-note,
  .st-admin .st-flash {
    font-size: 13px;
  }
  .st-admin .st-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .st-admin .st-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .st-admin .st-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .st-admin .st-stat strong {
    display: block;
    font-size: 22px;
  }
  .st-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .st-admin th,
  .st-admin td {
    text-align: left;
    vertical-align: top;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
  }
</style>
<div class="st-admin" data-search-terms-admin>
  <form data-search-terms-form action="<?= $h(
      $this->service->routeUrl('search_terms.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('search_terms.reset')
) ?>" data-rebuild-url="<?= $h(
    $this->service->routeUrl('search_terms.rebuild')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="st-note">
      Search Terms extracts lightweight internal-search summaries from tracked path query strings. It only reports aggregate terms and landing paths, never raw visitor identities.
    </div>

    <section class="st-section">
      <h5>Settings</h5>
      <div class="st-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty(
            $config['enabled']
        )
            ? 'checked'
            : '' ?>> Enable search terms reporting</label>
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
        <label>Minimum term length
          <input type="number" name="min_term_length" min="1" max="20" value="<?= $h(
              $config['min_term_length'] ?? 2
          ) ?>">
        </label>
        <label>Max top terms
          <input type="number" name="max_terms" min="10" max="500" value="<?= $h(
              $config['max_terms'] ?? 100
          ) ?>">
        </label>
      </div>
      <div class="st-grid">
        <label>Query parameter names
          <textarea name="query_params" spellcheck="false"><?= $h(
              implode("\n", (array) ($config['query_params'] ?? []))
          ) ?></textarea>
        </label>
        <label>Excluded terms
          <textarea name="exclude_terms" spellcheck="false"><?= $h(
              implode("\n", (array) ($config['exclude_terms'] ?? []))
          ) ?></textarea>
        </label>
      </div>
      <div class="st-note">
        Default query parameters are <code>q</code>, <code>s</code>, <code>search</code>, <code>query</code>, and <code>term</code>. Use one value per line or comma-separated.
      </div>
    </section>

    <section class="st-section">
      <h5>Reporting</h5>
      <div class="st-stats">
        <div class="st-stat"><span>Visits Scanned</span><strong><?= (int) ($report['summary']['visits_scanned'] ?? 0) ?></strong></div>
        <div class="st-stat"><span>Search Visits</span><strong><?= (int) ($report['summary']['search_visits'] ?? 0) ?></strong></div>
        <div class="st-stat"><span>Unique Terms</span><strong><?= (int) ($report['summary']['unique_terms'] ?? 0) ?></strong></div>
      </div>

      <?php if (!empty($report['notes'])): ?>
        <?php foreach ($report['notes'] as $note): ?>
          <div class="st-note"><?= $h($note) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <h6>Top Terms</h6>
      <table>
        <thead><tr><th>Term</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_terms'])): ?>
            <?php foreach ($report['top_terms'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No search terms found in this range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Top Search Paths</h6>
      <table>
        <thead><tr><th>Path</th><th>Search Visits</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_paths'])): ?>
            <?php foreach ($report['top_paths'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No search paths found in this range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Trend By Day</h6>
      <table>
        <thead><tr><th>Day</th><th>Search Visits</th></tr></thead>
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

    <div class="st-actions">
      <button type="submit" class="button btn disable">Save Search Terms Settings</button>
      <button type="button" class="button" data-search-terms-rebuild>Refresh / Rebuild Report</button>
      <button type="button" class="button" data-search-terms-reset>Reset To Defaults</button>
    </div>
    <div class="st-flash" data-search-terms-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-search-terms-admin')) {
    root = document.querySelector('[data-search-terms-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-search-terms-form]');
  var flash = root.querySelector('[data-search-terms-flash]');
  var resetBtn = root.querySelector('[data-search-terms-reset]');
  var rebuildBtn = root.querySelector('[data-search-terms-rebuild]');

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
      if (!confirm('Reset Search Terms to defaults?')) return;
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
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name=\"csrf\"]').value || ''));
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
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name=\"csrf\"]').value || ''));
    });
  }
})();
</script>
