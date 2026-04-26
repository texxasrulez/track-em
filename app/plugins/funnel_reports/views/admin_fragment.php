<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$funnelsList = is_array($config['funnels'] ?? null) ? $config['funnels'] : [];
?>
<style>
  .fr-admin { border: 1px solid var(--border); border-radius: 12px; padding: 14px; background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent); }
  .fr-admin .fr-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 10px 12px; margin: 10px 0 12px; }
  .fr-admin .fr-grid label { display: block; font-size: 13px; }
  .fr-admin input[type="text"], .fr-admin textarea, .fr-admin select { width: 100%; box-sizing: border-box; }
  .fr-admin textarea { min-height: 110px; resize: vertical; font-family: inherit; }
  .fr-admin .fr-note, .fr-admin .fr-flash { font-size: 13px; }
  .fr-admin .fr-flash { min-height: 18px; margin-top: 10px; }
  .fr-admin .fr-section + .fr-admin .fr-section, .fr-admin .fr-section + .fr-section { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border); }
  .fr-admin .fr-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 12px; align-items: center; }
  .fr-admin .fr-actions .button, .fr-admin .fr-actions button { width: auto; flex: 0 0 auto; }
  .fr-admin .fr-stat-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin: 10px 0 14px; }
  .fr-admin .fr-stat { padding: 12px; border: 1px solid var(--border); border-radius: 10px; background: rgba(255,255,255,0.45); }
  .fr-admin .fr-stat strong { display: block; font-size: 22px; }
  .fr-admin table { width: 100%; border-collapse: collapse; margin-top: 10px; }
  .fr-admin th, .fr-admin td { text-align: left; vertical-align: top; padding: 6px 8px; border-bottom: 1px solid var(--border); font-size: 13px; }
</style>
<div class="fr-admin" data-funnel-reports-admin>
  <form data-funnel-reports-form action="<?= $h($this->service->routeUrl('funnel_reports.save')) ?>" data-reset-url="<?= $h($this->service->routeUrl('funnel_reports.reset')) ?>" data-rebuild-url="<?= $h($this->service->routeUrl('funnel_reports.rebuild')) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="fr-note">
      Funnel Reports shows aggregate step counts and drop-offs over existing visits and optional events. It does not reconstruct individual user journeys or sessions.
    </div>

    <section class="fr-section">
      <h5>Settings</h5>
      <div class="fr-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty($config['enabled']) ? 'checked' : '' ?>> Enable funnel reports</label>
        <label>Report range
          <select name="report_range">
            <option value="today" <?= ($config['report_range'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="7d" <?= ($config['report_range'] ?? '') === '7d' ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30d" <?= ($config['report_range'] ?? '30d') === '30d' ? 'selected' : '' ?>>Last 30 days</option>
            <option value="all" <?= ($config['report_range'] ?? '') === 'all' ? 'selected' : '' ?>>All time</option>
          </select>
        </label>
      </div>
      <?php if (!$eventTrackingAvailable): ?>
        <div class="fr-note">Event steps are available in the syntax below, but they require the `event_tracking` plugin to be installed.</div>
      <?php endif; ?>
    </section>

    <section class="fr-section">
      <h5>Funnels</h5>
      <table data-funnels-table>
        <thead><tr><th>Name</th><th>Steps</th><th>Active</th><th></th></tr></thead>
        <tbody data-funnels-body>
          <?php foreach ($funnelsList as $index => $funnel): ?>
            <tr data-funnel-row>
              <td>
                <input type="hidden" name="funnels[<?= (int) $index ?>][id]" value="<?= $h($funnel['id'] ?? '') ?>">
                <input type="text" name="funnels[<?= (int) $index ?>][name]" value="<?= $h($funnel['name'] ?? '') ?>" maxlength="80">
              </td>
              <td>
                <textarea name="funnels[<?= (int) $index ?>][steps_text]" spellcheck="false"><?= $h($funnel['steps_text'] ?? '') ?></textarea>
                <div class="fr-note">One step per line: <code>Name|contains_path|/checkout</code> or <code>Signup|event_name|signup_submit|footer</code></div>
              </td>
              <td><input type="checkbox" name="funnels[<?= (int) $index ?>][active]" value="1" <?= !empty($funnel['active']) ? 'checked' : '' ?>></td>
              <td><button type="button" class="button danger" data-remove-funnel>Delete</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="fr-actions">
        <button type="button" class="button" data-add-funnel>Add Funnel</button>
      </div>
    </section>

    <section class="fr-section">
      <h5>Reporting</h5>
      <?php if (!empty($report['notes'])): foreach ($report['notes'] as $note): ?>
        <div class="fr-note"><?= $h($note) ?></div>
      <?php endforeach; endif; ?>

      <?php if (!empty($report['funnels'])): ?>
        <?php foreach ($report['funnels'] as $funnel): ?>
          <div class="fr-section">
            <h6><?= $h($funnel['name'] ?? '') ?></h6>
            <div class="fr-stat-grid">
              <div class="fr-stat"><span>Steps</span><strong><?= (int) count((array) ($funnel['steps'] ?? [])) ?></strong></div>
              <div class="fr-stat"><span>Final Count</span><strong><?= (int) ($funnel['final_count'] ?? 0) ?></strong></div>
              <div class="fr-stat"><span>Conversion</span><strong><?= $h(number_format((float) ($funnel['conversion_rate'] ?? 0), 2)) ?>%</strong></div>
            </div>
            <?php if (!empty($funnel['event_unavailable'])): ?>
              <div class="fr-note">This funnel includes event steps, but the `event_tracking` plugin is not installed.</div>
            <?php endif; ?>
            <table>
              <thead><tr><th>Step</th><th>Type</th><th>Match</th><th>Count</th><th>Drop-off</th><th>Conversion</th></tr></thead>
              <tbody>
                <?php foreach ((array) ($funnel['steps'] ?? []) as $step): ?>
                  <tr>
                    <td><?= $h($step['name'] ?? '') ?></td>
                    <td><?= $h($step['type'] ?? '') ?></td>
                    <td><?= $h($step['match_value'] ?? '') ?><?= !empty($step['label_match']) ? ' | ' . $h($step['label_match']) : '' ?></td>
                    <td><?= (int) ($step['count'] ?? 0) ?></td>
                    <td><?= (int) ($step['drop_off'] ?? 0) ?></td>
                    <td><?= $h(number_format((float) ($step['conversion_rate'] ?? 0), 2)) ?>%</td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <h6 style="margin-top:12px">Final-Step Trend</h6>
            <table>
              <thead><tr><th>Day</th><th>Count</th></tr></thead>
              <tbody>
                <?php if (!empty($funnel['trend'])): foreach ($funnel['trend'] as $row): ?>
                  <tr><td><?= $h($row['day'] ?? '') ?></td><td><?= (int) ($row['count'] ?? 0) ?></td></tr>
                <?php endforeach; else: ?>
                  <tr><td colspan="2">No trend data available.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="fr-note">No funnels configured yet.</div>
      <?php endif; ?>
    </section>

    <div class="fr-actions">
      <button type="submit" class="button btn disable">Save Funnel Reports Settings</button>
      <button type="button" class="button" data-funnel-reports-rebuild>Refresh / Rebuild Report</button>
      <button type="button" class="button" data-funnel-reports-reset>Reset To Defaults</button>
    </div>
    <div class="fr-flash" data-funnel-reports-flash></div>
  </form>
</div>
<template id="funnel-row-template">
  <tr data-funnel-row>
    <td>
      <input type="hidden" data-field="id" value="">
      <input type="text" data-field="name" maxlength="80" value="">
    </td>
    <td>
      <textarea data-field="steps_text" spellcheck="false"></textarea>
      <div class="fr-note">One step per line: <code>Name|contains_path|/checkout</code> or <code>Signup|event_name|signup_submit|footer</code></div>
    </td>
    <td><input type="checkbox" data-field="active" value="1" checked></td>
    <td><button type="button" class="button danger" data-remove-funnel>Delete</button></td>
  </tr>
</template>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-funnel-reports-admin')) {
    root = document.querySelector('[data-funnel-reports-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-funnel-reports-form]');
  var flash = root.querySelector('[data-funnel-reports-flash]');
  var body = root.querySelector('[data-funnels-body]');
  var tpl = document.getElementById('funnel-row-template');

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
  function renumberRows(){
    if (!body) return;
    var rows = body.querySelectorAll('[data-funnel-row]');
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      var map = {id:'funnels['+i+'][id]', name:'funnels['+i+'][name]', steps_text:'funnels['+i+'][steps_text]', active:'funnels['+i+'][active]'};
      Object.keys(map).forEach(function(key){
        var field = row.querySelector('[data-field="'+key+'"]') || row.querySelector('[name$="['+key+']"]');
        if (field) field.name = map[key];
      });
    }
  }
  function bindRow(row){
    if (!row) return;
    stopDragPropagation(row);
    var removeBtn = row.querySelector('[data-remove-funnel]');
    if (removeBtn) {
      stopDragPropagation(removeBtn);
      removeBtn.addEventListener('click', function(){
        row.remove();
        renumberRows();
      });
    }
    Array.prototype.forEach.call(row.querySelectorAll('textarea,input,select,button'), stopDragPropagation);
  }

  Array.prototype.forEach.call(root.querySelectorAll('[data-funnel-row]'), bindRow);

  var addBtn = root.querySelector('[data-add-funnel]');
  if (addBtn && tpl && body) {
    stopDragPropagation(addBtn);
    addBtn.addEventListener('click', function(){
      var node = tpl.content.firstElementChild.cloneNode(true);
      var idField = node.querySelector('[data-field="id"]');
      if (idField) idField.value = '';
      bindRow(node);
      body.appendChild(node);
      renumberRows();
    });
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

  var resetBtn = root.querySelector('[data-funnel-reports-reset]');
  if (resetBtn && form) {
    stopDragPropagation(resetBtn);
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Funnel Reports to defaults?')) return;
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
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }

  var rebuildBtn = root.querySelector('[data-funnel-reports-rebuild]');
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
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }
})();
</script>
