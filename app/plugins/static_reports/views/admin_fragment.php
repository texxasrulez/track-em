<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$sections = is_array($config['include_sections'] ?? null) ? $config['include_sections'] : [];
?>
<style>
  .sr-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .sr-admin .sr-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .sr-admin .sr-grid label {
    display: block;
    font-size: 13px;
  }
  .sr-admin input[type="number"] {
    width: 100%;
    box-sizing: border-box;
  }
  .sr-admin input[type="email"] {
    width: 100%;
    box-sizing: border-box;
  }
  .sr-admin .sr-section + .sr-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .sr-admin .sr-note,
  .sr-admin .sr-flash {
    font-size: 13px;
  }
  .sr-admin .sr-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .sr-admin .sr-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
    margin-top: 12px;
  }
  .sr-admin .sr-actions .button,
  .sr-admin .sr-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .sr-admin .sr-badges {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-top: 8px;
  }
  .sr-admin .sr-badge {
    display: inline-block;
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 3px 9px;
    font-size: 12px;
  }
  .sr-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }
  .sr-admin th,
  .sr-admin td {
    text-align: left;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: top;
  }
</style>
<div class="sr-admin" data-static-reports-admin>
  <form
    data-static-reports-form
    action="<?= $h($this->service->routeUrl('static_reports.save')) ?>"
    data-reset-url="<?= $h($this->service->routeUrl('static_reports.reset')) ?>"
    data-generate-url="<?= $h($this->service->routeUrl('static_reports.generate')) ?>"
  >
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="sr-note">
      Static Reports stores sanitized HTML snapshots under plugin storage so admin review uses cached files instead of live expensive reporting. Reports are admin-only through this plugin route.
    </div>

    <section class="sr-section">
      <h5>Generation</h5>
      <div class="sr-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable static reports</label>
        <label><input type="checkbox" name="generate_daily" value="1" <?= !empty($config['generate_daily']) ? 'checked' : '' ?>> Generate daily report</label>
        <label><input type="checkbox" name="generate_weekly" value="1" <?= !empty($config['generate_weekly']) ? 'checked' : '' ?>> Generate weekly report</label>
        <label><input type="checkbox" name="email_enabled" value="1" <?= !empty($config['email_enabled']) ? 'checked' : '' ?>> Email generated reports</label>
        <label>Email recipient
          <input type="email" name="email_recipient" value="<?= $h($config['email_recipient'] ?? '') ?>" placeholder="admin@example.com">
        </label>
        <label>Daily retention count
          <input type="number" min="1" max="365" name="retention_daily" value="<?= $h($config['retention_daily'] ?? 30) ?>">
        </label>
        <label>Weekly retention count
          <input type="number" min="1" max="104" name="retention_weekly" value="<?= $h($config['retention_weekly'] ?? 12) ?>">
        </label>
        <label><input type="checkbox" name="include_private_detail" value="1" <?= !empty($config['include_private_detail']) ? 'checked' : '' ?>> Include private detail in admin reports</label>
      </div>
      <div class="sr-note">
        Last automatic due check:
        <?= !empty($state['last_auto_check_ts']) ? $h(date('Y-m-d H:i:s', (int) $state['last_auto_check_ts'])) : 'Never' ?>.
        <?php if (!empty($autoResult['generated'])): ?>
          Generated <?= $h((string) count((array) $autoResult['generated'])) ?> due report(s) on this load.
        <?php endif; ?>
        <?php if (!empty($state['last_email_ts'])): ?>
          Last email attempt:
          <?= $h(date('Y-m-d H:i:s', (int) $state['last_email_ts'])) ?>
          to <?= $h((string) ($state['last_email_recipient'] ?? '')) ?>.
        <?php endif; ?>
        <?php if (!empty($state['last_email_error'])): ?>
          Email status: <?= $h((string) $state['last_email_error']) ?>.
        <?php endif; ?>
      </div>
    </section>

    <section class="sr-section">
      <h5>Included Sections</h5>
      <div class="sr-grid">
        <label><input type="checkbox" name="include_traffic_summary" value="1" <?= !empty($sections['traffic_summary']) ? 'checked' : '' ?>> Traffic summary</label>
        <label><input type="checkbox" name="include_top_paths" value="1" <?= !empty($sections['top_paths']) ? 'checked' : '' ?>> Top paths</label>
        <label><input type="checkbox" name="include_referrer_summary" value="1" <?= !empty($sections['referrer_summary']) ? 'checked' : '' ?>> Referrer summary</label>
        <label><input type="checkbox" name="include_event_summary" value="1" <?= !empty($sections['event_summary']) ? 'checked' : '' ?>> Event summary</label>
        <label><input type="checkbox" name="include_goals_summary" value="1" <?= !empty($sections['goals_summary']) ? 'checked' : '' ?>> Goals summary</label>
        <label><input type="checkbox" name="include_bot_summary" value="1" <?= !empty($sections['bot_summary']) ? 'checked' : '' ?>> Bot summary</label>
      </div>
      <div class="sr-badges">
        <span class="sr-badge">Referrer Intel: <?= !empty($capabilities['referrer_intel']) ? 'available' : 'missing' ?></span>
        <span class="sr-badge">Event Tracking: <?= !empty($capabilities['event_tracking']) ? 'available' : 'missing' ?></span>
        <span class="sr-badge">Goals: <?= !empty($capabilities['goals']) ? 'available' : 'missing' ?></span>
        <span class="sr-badge">Bot Watch: <?= !empty($capabilities['bot_watch']) ? 'available' : 'missing' ?></span>
      </div>
    </section>

    <section class="sr-section">
      <h5>Stored Reports</h5>
      <table>
        <thead><tr><th>Report</th><th>Type</th><th>Generated</th><th>Size</th><th>Action</th></tr></thead>
        <tbody>
          <?php if ($reports): ?>
            <?php foreach ($reports as $report): ?>
              <tr>
                <td><?= $h((string) ($report['label'] ?? '')) ?></td>
                <td><?= $h((string) ($report['type'] ?? '')) ?></td>
                <td><?= !empty($report['generated_at']) ? $h(date('Y-m-d H:i:s', (int) $report['generated_at'])) : 'Unknown' ?></td>
                <td><?= $h((string) round(((int) ($report['size_bytes'] ?? 0)) / 1024, 1)) ?> KB</td>
                <td><a class="button" href="<?= $h((string) ($report['view_url'] ?? '#')) ?>" target="_blank" rel="noopener">View Report</a></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5">No static reports have been generated yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="sr-actions">
      <button type="submit" class="button btn disable">Save Static Reports Settings</button>
      <button type="button" class="button" data-static-reports-generate>Generate Now</button>
      <button type="button" class="button" data-static-reports-reset>Reset To Defaults</button>
    </div>
    <div class="sr-flash" data-static-reports-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-static-reports-admin')) {
    root = document.querySelector('[data-static-reports-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-static-reports-form]');
  var flash = root.querySelector('[data-static-reports-flash]');
  var resetBtn = root.querySelector('[data-static-reports-reset]');
  var generateBtn = root.querySelector('[data-static-reports-generate]');

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
      if (!confirm('Reset Static Reports to defaults?')) return;
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
      setFlash('Generating reports...', false);
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
