<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$jobs = is_array($config['jobs'] ?? null) ? $config['jobs'] : [];
?>
<style>
  .sch-admin { border: 1px solid var(--border); border-radius: 12px; padding: 14px; background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent); }
  .sch-admin .sch-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px 12px; margin: 10px 0 12px; }
  .sch-admin .sch-grid label { display: block; font-size: 13px; }
  .sch-admin input[type="number"], .sch-admin select { width: 100%; box-sizing: border-box; }
  .sch-admin .sch-section + .sch-section { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border); }
  .sch-admin .sch-note, .sch-admin .sch-flash { font-size: 13px; }
  .sch-admin .sch-flash { min-height: 18px; margin-top: 10px; }
  .sch-admin .sch-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; align-items: center; }
  .sch-admin .sch-actions .button, .sch-admin .sch-actions button { width: auto; flex: 0 0 auto; }
  .sch-admin table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  .sch-admin th, .sch-admin td { text-align: left; vertical-align: top; padding: 6px 8px; border-bottom: 1px solid var(--border); font-size: 13px; }
</style>
<div class="sch-admin" data-scheduler-admin>
  <form data-scheduler-form action="<?= $h($this->service->routeUrl('scheduler.save')) ?>" data-reset-url="<?= $h($this->service->routeUrl('scheduler.reset')) ?>" data-run-due-url="<?= $h($this->service->routeUrl('scheduler.run_due')) ?>" data-run-all-url="<?= $h($this->service->routeUrl('scheduler.run_all')) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="sch-note">
      Scheduler runs a small set of known plugin maintenance jobs when an admin loads the plugin page or triggers a manual run. It is intentionally lightweight and not a background daemon.
    </div>

    <section class="sch-section">
      <h5>Settings</h5>
      <div class="sch-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable scheduler</label>
        <label><input type="checkbox" name="run_on_admin_load" value="1" <?= !empty($config['run_on_admin_load']) ? 'checked' : '' ?>> Run due jobs on admin load</label>
        <label>Admin-load cooldown minutes
          <input type="number" name="admin_cooldown_minutes" min="1" max="1440" value="<?= $h($config['admin_cooldown_minutes'] ?? 5) ?>">
        </label>
        <label>Max jobs per tick
          <input type="number" name="max_jobs_per_tick" min="1" max="10" value="<?= $h($config['max_jobs_per_tick'] ?? 3) ?>">
        </label>
      </div>
      <div class="sch-note">
        Last admin tick:
        <?= !empty($state['last_admin_tick_ts']) ? $h(date('Y-m-d H:i:s', (int) $state['last_admin_tick_ts'])) : 'Never' ?>.
        Auto-run result: <?= $h((string) ($autoResult['reason'] ?? 'n/a')) ?>.
        <?php if (!empty($state['last_error'])): ?>Last error: <?= $h((string) $state['last_error']) ?>.<?php endif; ?>
      </div>
    </section>

    <section class="sch-section">
      <h5>Jobs</h5>
      <table>
        <thead><tr><th>Job</th><th>Interval Minutes</th><th>Active</th></tr></thead>
        <tbody>
          <?php foreach ($jobs as $index => $job): ?>
            <?php $meta = $availableJobs[(string) ($job['job_id'] ?? '')] ?? null; ?>
            <tr>
              <td>
                <input type="hidden" name="jobs[<?= (int) $index ?>][job_id]" value="<?= $h($job['job_id'] ?? '') ?>">
                <strong><?= $h($meta['label'] ?? ($job['job_id'] ?? 'Unknown')) ?></strong>
                <div class="sch-note"><?= $h($meta['description'] ?? '') ?></div>
              </td>
              <td><input type="number" name="jobs[<?= (int) $index ?>][interval_minutes]" min="1" max="10080" value="<?= $h($job['interval_minutes'] ?? 60) ?>"></td>
              <td><input type="checkbox" name="jobs[<?= (int) $index ?>][active]" value="1" <?= !empty($job['active']) ? 'checked' : '' ?>></td>
            </tr>
          <?php endforeach; ?>
          <?php if (!$jobs): ?>
            <tr><td colspan="3">No supported scheduler jobs are currently configured.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <section class="sch-section">
      <h5>Recent Runs</h5>
      <table>
        <thead><tr><th>Time</th><th>Job</th><th>Status</th><th>Summary</th></tr></thead>
        <tbody>
          <?php if (!empty($runs)): ?>
            <?php foreach ($runs as $run): ?>
              <?php $meta = $availableJobs[(string) ($run['job_id'] ?? '')] ?? null; ?>
              <tr>
                <td><?= !empty($run['ts']) ? $h(date('Y-m-d H:i:s', (int) $run['ts'])) : 'Unknown' ?></td>
                <td><?= $h($meta['label'] ?? ($run['job_id'] ?? 'Unknown')) ?></td>
                <td><?= !empty($run['ok']) ? 'OK' : 'Error' ?></td>
                <td><?= $h((string) ($run['summary'] ?? '')) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4">No scheduler runs recorded yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="sch-actions">
      <button type="submit" class="button btn disable">Save Scheduler Settings</button>
      <button type="button" class="button" data-scheduler-run-due>Run Due Jobs Now</button>
      <button type="button" class="button" data-scheduler-run-all>Run All Active Jobs</button>
      <button type="button" class="button" data-scheduler-reset>Reset To Defaults</button>
    </div>
    <div class="sch-flash" data-scheduler-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-scheduler-admin')) {
    root = document.querySelector('[data-scheduler-admin]:last-of-type');
  }
  if (!root) return;
  var form = root.querySelector('[data-scheduler-form]');
  var flash = root.querySelector('[data-scheduler-flash]');
  function setFlash(msg, isError){
    if (!flash) return;
    flash.textContent = msg || '';
    flash.style.color = isError ? '#c2410c' : 'var(--text)';
  }
  function stopDragPropagation(el){
    if (!el) return;
    ['mousedown','pointerdown','dragstart','touchstart'].forEach(function(type){
      el.addEventListener(type, function(e){ e.stopPropagation(); });
    });
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
      post(form.getAttribute('action') || '', encodeFormData(form), 'Settings saved.');
    });
  }
  [['[data-scheduler-reset]','data-reset-url','Reset Scheduler to defaults?','Defaults restored.'],
   ['[data-scheduler-run-due]','data-run-due-url',null,'Due jobs finished.'],
   ['[data-scheduler-run-all]','data-run-all-url',null,'All active jobs finished.']].forEach(function(item){
    var btn = root.querySelector(item[0]);
    if (!btn || !form) return;
    stopDragPropagation(btn);
    btn.addEventListener('click', function(){
      if (item[2] && !confirm(item[2])) return;
      var body = 'csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || '');
      post(form.getAttribute(item[1]) || '', body, item[3]);
    });
  });
})();
</script>
