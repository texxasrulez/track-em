<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$goalsList = is_array($config['goals'] ?? null) ? $config['goals'] : [];
?>
<style>
  .goals-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .goals-admin .goals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .goals-admin .goals-grid label {
    display: block;
    font-size: 13px;
  }
  .goals-admin input[type="text"],
  .goals-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .goals-admin .goals-note,
  .goals-admin .goals-flash {
    font-size: 13px;
  }
  .goals-admin .goals-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .goals-admin .goals-section + .goals-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .goals-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .goals-admin th,
  .goals-admin td {
    text-align: left;
    vertical-align: top;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
  }
  .goals-admin .goals-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    align-items: center;
  }
  .goals-admin .goals-report-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .goals-admin .goals-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .goals-admin .goals-stat strong {
    display: block;
    font-size: 22px;
  }
  .goals-admin .goals-actions .button,
  .goals-admin .goals-actions button {
    width: auto;
    flex: 0 0 auto;
  }
</style>
<div class="goals-admin" data-goals-admin>
  <form data-goals-form action="<?= $h($this->service->routeUrl('goals.save')) ?>" data-reset-url="<?= $h(
      $this->service->routeUrl('goals.reset')
  ) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="goals-note">
      Goals are computed from existing page visits and, when available, event_tracking events. No private visitor identifiers are shown here.
    </div>

    <section class="goals-section">
      <h5>Settings</h5>
      <div class="goals-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty(
            $config['enabled']
        )
            ? 'checked'
            : '' ?>> Enable goals reporting</label>
        <label>Report date range
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
      </div>
      <?php if (!$eventTrackingAvailable): ?>
        <div class="goals-note">Event goals are unavailable because the `event_tracking` plugin is not installed.</div>
      <?php endif; ?>
    </section>

    <section class="goals-section">
      <h5>Goals</h5>
      <table data-goals-table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Type</th>
            <th>Match</th>
            <th>Label Match</th>
            <th>Active</th>
            <th></th>
          </tr>
        </thead>
        <tbody data-goals-body>
          <?php foreach ($goalsList as $index => $goal): ?>
            <tr data-goal-row>
              <td>
                <input type="hidden" name="goals[<?= (int) $index ?>][id]" value="<?= $h(
                    $goal['id'] ?? ''
                ) ?>">
                <input type="text" name="goals[<?= (int) $index ?>][name]" value="<?= $h(
                    $goal['name'] ?? ''
                ) ?>" maxlength="80">
              </td>
              <td>
                <select name="goals[<?= (int) $index ?>][type]" data-goal-type>
                  <option value="path_match" <?= ($goal['type'] ?? '') === 'path_match'
                      ? 'selected'
                      : '' ?>>Path match</option>
                  <option value="exact_path" <?= ($goal['type'] ?? 'exact_path') === 'exact_path'
                      ? 'selected'
                      : '' ?>>Exact path</option>
                  <option value="contains_path" <?= ($goal['type'] ?? '') === 'contains_path'
                      ? 'selected'
                      : '' ?>>Contains path</option>
                  <option value="event_name" <?= ($goal['type'] ?? '') === 'event_name'
                      ? 'selected'
                      : '' ?> <?= !$eventTrackingAvailable ? 'disabled' : '' ?>>Event name</option>
                </select>
              </td>
              <td><input type="text" name="goals[<?= (int) $index ?>][match_value]" value="<?= $h(
                  $goal['match_value'] ?? ''
              ) ?>" maxlength="255"></td>
              <td><input type="text" name="goals[<?= (int) $index ?>][label_match]" value="<?= $h(
                  $goal['label_match'] ?? ''
              ) ?>" maxlength="100" data-goal-label></td>
              <td><input type="checkbox" name="goals[<?= (int) $index ?>][active]" value="1" <?= !empty(
                  $goal['active']
              )
                  ? 'checked'
                  : '' ?>></td>
              <td><button type="button" class="button danger" data-remove-goal>Delete</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="goals-actions">
        <button type="button" class="button" data-add-goal>Add Goal</button>
      </div>
    </section>

    <section class="goals-section">
      <h5>Reporting</h5>
      <div class="goals-report-grid">
        <div class="goals-stat"><span>Visits</span><strong><?= (int) ($report['totals']['visits'] ?? 0) ?></strong></div>
        <div class="goals-stat"><span>Completions</span><strong><?= (int) ($report['totals']['completions'] ?? 0) ?></strong></div>
      </div>

      <?php if (!empty($report['notes'])): ?>
        <?php foreach ($report['notes'] as $note): ?>
          <div class="goals-note"><?= $h($note) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <h6>Goal Performance</h6>
      <table>
        <thead><tr><th>Goal</th><th>Type</th><th>Completions</th><th>Conversion Rate</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (!empty($report['goals'])): ?>
            <?php foreach ($report['goals'] as $goal): ?>
              <tr>
                <td><?= $h($goal['name']) ?></td>
                <td><?= $h($goal['type']) ?></td>
                <td><?= (int) $goal['completions'] ?></td>
                <td><?= $h(number_format((float) $goal['conversion_rate'], 2)) ?>%</td>
                <td><?= !empty($goal['event_unavailable'])
                    ? 'Event plugin missing'
                    : (!empty($goal['active']) ? 'Active' : 'Inactive') ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5">No goals configured.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Top Converting Paths</h6>
      <table>
        <thead><tr><th>Path</th><th>Completions</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_paths'])): ?>
            <?php foreach ($report['top_paths'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No conversion paths yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Trend By Day</h6>
      <table>
        <thead><tr><th>Day</th><th>Completions</th></tr></thead>
        <tbody>
          <?php if (!empty($report['trend'])): ?>
            <?php foreach ($report['trend'] as $row): ?>
              <tr><td><?= $h($row['day']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No trend data yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="goals-actions">
      <button type="submit" class="button btn disable">Save Goals Settings</button>
      <button type="button" class="button" data-goals-reset>Reset To Defaults</button>
    </div>
    <div class="goals-flash" data-goals-flash></div>
  </form>
</div>
<template id="goals-row-template">
  <tr data-goal-row>
    <td>
      <input type="hidden" data-field="id" value="">
      <input type="text" data-field="name" value="" maxlength="80">
    </td>
    <td>
      <select data-field="type" data-goal-type>
        <option value="path_match">Path match</option>
        <option value="exact_path">Exact path</option>
        <option value="contains_path">Contains path</option>
        <option value="event_name" <?= !$eventTrackingAvailable ? 'disabled' : '' ?>>Event name</option>
      </select>
    </td>
    <td><input type="text" data-field="match_value" value="" maxlength="255"></td>
    <td><input type="text" data-field="label_match" value="" maxlength="100" data-goal-label></td>
    <td><input type="checkbox" data-field="active" value="1" checked></td>
    <td><button type="button" class="button danger" data-remove-goal>Delete</button></td>
  </tr>
</template>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-goals-admin')) {
    root = document.querySelector('[data-goals-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-goals-form]');
  var body = root.querySelector('[data-goals-body]');
  var flash = root.querySelector('[data-goals-flash]');
  var addBtn = root.querySelector('[data-add-goal]');
  var resetBtn = root.querySelector('[data-goals-reset]');
  var tpl = document.getElementById('goals-row-template');

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

  function updateLabelState(row){
    if (!row) return;
    var type = row.querySelector('[data-goal-type]');
    var label = row.querySelector('[data-goal-label]');
    if (!type || !label) return;
    label.disabled = type.value !== 'event_name';
    if (label.disabled) {
      label.value = '';
    }
  }

  function reindexRows(){
    var rows = body.querySelectorAll('[data-goal-row]');
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      var fields = row.querySelectorAll('[data-field], [name^="goals["]');
      for (var j = 0; j < fields.length; j++) {
        var field = fields[j];
        var key = field.getAttribute('data-field');
        if (!key) {
          var match = field.name.match(/\]\[([^\]]+)\]$/);
          key = match ? match[1] : '';
        }
        if (!key) continue;
        field.name = 'goals[' + i + '][' + key + ']';
      }
      updateLabelState(row);
    }
  }

  function wireRow(row){
    if (!row) return;
    stopDragPropagation(row);
    var removeBtn = row.querySelector('[data-remove-goal]');
    var typeSel = row.querySelector('[data-goal-type]');
    if (removeBtn) {
      stopDragPropagation(removeBtn);
      removeBtn.addEventListener('click', function(){
        row.parentNode.removeChild(row);
        reindexRows();
      });
    }
    if (typeSel) {
      typeSel.addEventListener('change', function(){
        updateLabelState(row);
      });
    }
    updateLabelState(row);
  }

  function addRow(){
    if (!tpl || !body) return;
    var fragment = tpl.content.cloneNode(true);
    var row = fragment.querySelector('[data-goal-row]');
    body.appendChild(fragment);
    row = body.querySelectorAll('[data-goal-row]')[body.querySelectorAll('[data-goal-row]').length - 1];
    wireRow(row);
    reindexRows();
  }

  if (body) {
    var rows = body.querySelectorAll('[data-goal-row]');
    for (var i = 0; i < rows.length; i++) {
      wireRow(rows[i]);
    }
    reindexRows();
  }

  if (addBtn) {
    stopDragPropagation(addBtn);
    addBtn.addEventListener('click', function(){
      addRow();
    });
  }

  if (form) {
    stopDragPropagation(form);
    form.addEventListener('submit', function(e){
      e.preventDefault();
      reindexRows();
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
        setFlash('Settings saved. Reload the Plugins page to refresh reports.', false);
      };
      xhr.send(encodeFormData(form));
    });
  }

  if (resetBtn && form) {
    stopDragPropagation(resetBtn);
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Goals to defaults?')) return;
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
})();
</script>
