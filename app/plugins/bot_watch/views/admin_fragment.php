<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$joinLines = static fn(array $items): string => implode("\n", array_map('strval', $items));
?>
<style>
  .bw-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .bw-admin .bw-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .bw-admin .bw-grid label {
    display: block;
    font-size: 13px;
  }
  .bw-admin input[type="text"],
  .bw-admin input[type="number"],
  .bw-admin select,
  .bw-admin textarea {
    width: 100%;
    box-sizing: border-box;
  }
  .bw-admin textarea {
    min-height: 108px;
    resize: vertical;
  }
  .bw-admin .bw-section + .bw-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .bw-admin .bw-note,
  .bw-admin .bw-flash {
    font-size: 13px;
  }
  .bw-admin .bw-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .bw-admin .bw-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 12px;
  }
  .bw-admin .bw-actions .button,
  .bw-admin .bw-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .bw-admin .bw-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 10px;
    margin-top: 10px;
  }
  .bw-admin .bw-stat {
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    background: rgba(255,255,255,0.03);
  }
  .bw-admin .bw-stat strong {
    display: block;
    font-size: 22px;
    margin-top: 6px;
  }
  .bw-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }
  .bw-admin th,
  .bw-admin td {
    text-align: left;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: top;
  }
  .bw-admin .bw-tag {
    display: inline-block;
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 2px 8px;
    margin: 2px 6px 2px 0;
    font-size: 12px;
  }
  .bw-admin .bw-reasons {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }
</style>
<div class="bw-admin" data-bot-watch-admin>
  <form
    data-bot-watch-form
    action="<?= $h($this->service->routeUrl('bot_watch.save')) ?>"
    data-reset-url="<?= $h($this->service->routeUrl('bot_watch.reset')) ?>"
    data-rebuild-url="<?= $h($this->service->routeUrl('bot_watch.rebuild')) ?>"
  >
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="bw-note">
      Bot Watch is admin-only detection. It scores likely scrapers, scanners, and probers using simple explainable rules and does not block traffic.
    </div>

    <section class="bw-section">
      <h5>Settings</h5>
      <div class="bw-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable Bot Watch</label>
        <label>Sensitivity
          <select name="sensitivity">
            <?php foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $value => $label): ?>
              <option value="<?= $h($value) ?>" <?= ($config['sensitivity'] ?? 'normal') === $value ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label><input type="checkbox" name="ignore_known_good_bots" value="1" <?= !empty($config['ignore_known_good_bots']) ? 'checked' : '' ?>> Ignore known good bots</label>
        <label>Max hits per minute threshold
          <input type="number" name="max_hits_per_minute_threshold" min="5" max="500" value="<?= $h($config['max_hits_per_minute_threshold'] ?? 20) ?>">
        </label>
        <label>404 / probing threshold
          <input type="number" name="status_404_threshold" min="3" max="500" value="<?= $h($config['status_404_threshold'] ?? 12) ?>">
        </label>
      </div>
      <div class="bw-grid">
        <label>Known bot allowlist
          <textarea name="known_bot_allowlist"><?= $h($joinLines(is_array($config['known_bot_allowlist'] ?? null) ? $config['known_bot_allowlist'] : [])) ?></textarea>
        </label>
        <label>Suspicious path patterns
          <textarea name="suspicious_path_patterns"><?= $h($joinLines(is_array($config['suspicious_path_patterns'] ?? null) ? $config['suspicious_path_patterns'] : [])) ?></textarea>
        </label>
      </div>
    </section>

    <section class="bw-section">
      <h5>Summary</h5>
      <div class="bw-note">
        Last scan:
        <?= !empty($state['last_scan_ts']) ? $h(date('Y-m-d H:i:s', (int) $state['last_scan_ts'])) : 'Never' ?>.
        Status-aware scoring: <?= !empty($report['status_data_available']) ? 'available' : 'not available' ?>.
        Scan window: last <?= $h((string) ($report['scan_window_hours'] ?? 24)) ?> hours.
      </div>
      <div class="bw-stats">
        <div class="bw-stat">Flagged sources<strong><?= $h((string) ($report['summary']['sources_flagged'] ?? 0)) ?></strong></div>
        <div class="bw-stat">Recent detections<strong><?= $h((string) ($report['summary']['recent_detections'] ?? 0)) ?></strong></div>
        <div class="bw-stat">Rows analyzed<strong><?= $h((string) ($report['summary']['analysis_rows'] ?? 0)) ?></strong></div>
        <div class="bw-stat">New this scan<strong><?= $h((string) ($report['summary']['new_detections'] ?? 0)) ?></strong></div>
      </div>
      <?php if (!empty($report['notes'])): ?>
        <div class="bw-note" style="margin-top:10px;">
          <?php foreach ($report['notes'] as $note): ?>
            <div><?= $h((string) $note) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>

    <section class="bw-section">
      <h5>Score Explanation</h5>
      <div class="bw-note">
        Current sensitivity: <?= $h((string) ($report['score_legend']['sensitivity'] ?? 'normal')) ?>.
        Threshold: <?= $h((string) ($report['score_legend']['score_threshold'] ?? 0)) ?> points.
      </div>
      <table>
        <thead><tr><th>Rule</th><th>Points</th></tr></thead>
        <tbody>
          <?php foreach (($report['score_legend']['rules'] ?? []) as $rule): ?>
            <tr>
              <td><?= $h((string) ($rule['label'] ?? '')) ?></td>
              <td><?= $h((string) ($rule['points'] ?? '')) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </section>

    <section class="bw-section">
      <h5>Suspicious IPs / Ranges</h5>
      <table>
        <thead><tr><th>Source</th><th>Range</th><th>Score</th><th>Hits</th><th>Unique Paths</th><th>Reasons</th></tr></thead>
        <tbody>
          <?php if (!empty($report['suspicious_sources'])): ?>
            <?php foreach ($report['suspicious_sources'] as $row): ?>
              <tr>
                <td><?= $h((string) ($row['source'] ?? '')) ?></td>
                <td><?= $h((string) ($row['source_range'] ?? '')) ?></td>
                <td><?= $h((string) ($row['score'] ?? '')) ?></td>
                <td><?= $h((string) ($row['hits'] ?? '')) ?></td>
                <td><?= $h((string) ($row['unique_paths'] ?? '')) ?></td>
                <td>
                  <div class="bw-reasons">
                    <?php foreach (($row['reasons'] ?? []) as $reason): ?>
                      <span class="bw-tag"><?= $h((string) ($reason['label'] ?? '')) ?></span>
                    <?php endforeach; ?>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="6">No suspicious sources met the current score threshold.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <section class="bw-section">
      <h5>Suspicious User Agents</h5>
      <table>
        <thead><tr><th>User-Agent</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['suspicious_user_agents'])): ?>
            <?php foreach ($report['suspicious_user_agents'] as $row): ?>
              <tr>
                <td><?= $h((string) ($row['user_agent'] ?? '')) ?></td>
                <td><?= $h((string) ($row['count'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No suspicious user-agents were flagged.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <section class="bw-section">
      <h5>Suspicious Paths</h5>
      <table>
        <thead><tr><th>Path Pattern Hit</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['suspicious_paths'])): ?>
            <?php foreach ($report['suspicious_paths'] as $row): ?>
              <tr>
                <td><?= $h((string) ($row['path'] ?? '')) ?></td>
                <td><?= $h((string) ($row['count'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No configured probe paths were matched.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <section class="bw-section">
      <h5>Top Bot-Like Patterns</h5>
      <table>
        <thead><tr><th>Pattern</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_patterns'])): ?>
            <?php foreach ($report['top_patterns'] as $row): ?>
              <tr>
                <td><?= $h((string) ($row['pattern'] ?? '')) ?></td>
                <td><?= $h((string) ($row['count'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No bot-like patterns are currently dominant.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <section class="bw-section">
      <h5>Recent Detections</h5>
      <table>
        <thead><tr><th>When</th><th>Source</th><th>Score</th><th>Summary</th></tr></thead>
        <tbody>
          <?php if (!empty($report['recent_detections'])): ?>
            <?php foreach ($report['recent_detections'] as $row): ?>
              <tr>
                <td><?= $h(date('Y-m-d H:i:s', (int) ($row['ts'] ?? 0))) ?></td>
                <td><?= $h((string) ($row['source_range'] ?? ($row['source'] ?? ''))) ?></td>
                <td><?= $h((string) ($row['score'] ?? '')) ?></td>
                <td>
                  <?= $h((string) ($row['user_agent'] ?? '')) ?>
                  <?php if (!empty($row['path_samples']) && is_array($row['path_samples'])): ?>
                    <div class="bw-note">Paths: <?= $h(implode(', ', array_map('strval', array_slice($row['path_samples'], 0, 3)))) ?></div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4">No detections have been logged yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="bw-actions">
      <button type="submit" class="button btn disable">Save Bot Watch Settings</button>
      <button type="button" class="button" data-bot-watch-rebuild>Rebuild Detections</button>
      <button type="button" class="button" data-bot-watch-reset>Reset To Defaults</button>
    </div>
    <div class="bw-flash" data-bot-watch-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-bot-watch-admin')) {
    root = document.querySelector('[data-bot-watch-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-bot-watch-form]');
  var flash = root.querySelector('[data-bot-watch-flash]');
  var resetBtn = root.querySelector('[data-bot-watch-reset]');
  var rebuildBtn = root.querySelector('[data-bot-watch-rebuild]');

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
      if (!confirm('Reset Bot Watch to defaults?')) return;
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
      setFlash('Rebuilding detections...', false);
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
