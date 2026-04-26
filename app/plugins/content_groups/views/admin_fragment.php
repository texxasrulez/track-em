<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$groups = is_array($config['groups'] ?? null) ? $config['groups'] : [];
?>
<style>
  .cg-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .cg-admin .cg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .cg-admin .cg-grid label {
    display: block;
    font-size: 13px;
  }
  .cg-admin input[type="text"],
  .cg-admin input[type="number"],
  .cg-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .cg-admin .cg-section + .cg-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .cg-admin .cg-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    align-items: center;
  }
  .cg-admin .cg-actions .button,
  .cg-admin .cg-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .cg-admin .cg-note,
  .cg-admin .cg-flash {
    font-size: 13px;
  }
  .cg-admin .cg-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .cg-admin .cg-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .cg-admin .cg-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .cg-admin .cg-stat strong {
    display: block;
    font-size: 22px;
  }
  .cg-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .cg-admin th,
  .cg-admin td {
    text-align: left;
    vertical-align: top;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
  }
</style>
<div class="cg-admin" data-content-groups-admin>
  <form data-content-groups-form action="<?= $h(
      $this->service->routeUrl('content_groups.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('content_groups.reset')
) ?>" data-rebuild-url="<?= $h(
    $this->service->routeUrl('content_groups.rebuild')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="cg-note">
      Content Groups provides lightweight grouped reporting over existing paths. Rules are matched top to bottom, and the first active match wins.
    </div>

    <section class="cg-section">
      <h5>Settings</h5>
      <div class="cg-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty(
            $config['enabled']
        )
            ? 'checked'
            : '' ?>> Enable content groups</label>
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
        <label>Max group definitions
          <input type="number" name="max_groups" min="1" max="100" value="<?= $h(
              $config['max_groups'] ?? 40
          ) ?>">
        </label>
      </div>
    </section>

    <section class="cg-section">
      <h5>Group Rules</h5>
      <table data-content-groups-table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Match Type</th>
            <th>Rule</th>
            <th>Active</th>
            <th></th>
          </tr>
        </thead>
        <tbody data-content-groups-body>
          <?php foreach ($groups as $index => $group): ?>
            <tr data-content-group-row>
              <td>
                <input type="hidden" name="groups[<?= (int) $index ?>][id]" value="<?= $h(
                    $group['id'] ?? ''
                ) ?>">
                <input type="text" name="groups[<?= (int) $index ?>][name]" maxlength="80" value="<?= $h(
                    $group['name'] ?? ''
                ) ?>">
              </td>
              <td>
                <select name="groups[<?= (int) $index ?>][match_type]">
                  <?php foreach ($matchTypes as $type): ?>
                    <option value="<?= $h($type) ?>" <?= ($group['match_type'] ?? 'wildcard') === $type
                        ? 'selected'
                        : '' ?>><?= $h(ucfirst($type)) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="text" name="groups[<?= (int) $index ?>][rule]" maxlength="255" value="<?= $h(
                  $group['rule'] ?? ''
              ) ?>" placeholder="/blog/*"></td>
              <td><input type="checkbox" name="groups[<?= (int) $index ?>][active]" value="1" <?= !empty(
                  $group['active']
              )
                  ? 'checked'
                  : '' ?>></td>
              <td><button type="button" class="button danger" data-remove-content-group>Delete</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="cg-actions">
        <button type="button" class="button" data-add-content-group>Add Group</button>
      </div>
    </section>

    <section class="cg-section">
      <h5>Reporting</h5>
      <div class="cg-stats">
        <div class="cg-stat"><span>Total Visits</span><strong><?= (int) ($report['summary']['visits'] ?? 0) ?></strong></div>
        <div class="cg-stat"><span>Matched</span><strong><?= (int) ($report['summary']['matched_visits'] ?? 0) ?></strong></div>
        <div class="cg-stat"><span>Unmatched</span><strong><?= (int) ($report['summary']['unmatched_visits'] ?? 0) ?></strong></div>
      </div>

      <?php if (!empty($report['notes'])): ?>
        <?php foreach ($report['notes'] as $note): ?>
          <div class="cg-note"><?= $h($note) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <h6>Groups</h6>
      <table>
        <thead><tr><th>Group</th><th>Rule</th><th>Visits</th><th>Share</th><th>Top Paths</th></tr></thead>
        <tbody>
          <?php if (!empty($report['groups'])): ?>
            <?php foreach ($report['groups'] as $row): ?>
              <tr>
                <td><?= $h($row['name']) ?></td>
                <td><?= $h($row['match_type'] . ': ' . $row['rule']) ?></td>
                <td><?= (int) $row['visits'] ?></td>
                <td><?= $h(number_format((float) ($row['share'] ?? 0), 2)) ?>%</td>
                <td>
                  <?php if (!empty($row['top_paths'])): ?>
                    <?php foreach ($row['top_paths'] as $pathRow): ?>
                      <div><?= $h($pathRow['name']) ?> (<?= (int) $pathRow['count'] ?>)</div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    No matches yet.
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5">No content groups configured.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Top Unmatched Paths</h6>
      <table>
        <thead><tr><th>Path</th><th>Visits</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_unmatched_paths'])): ?>
            <?php foreach ($report['top_unmatched_paths'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No unmatched paths in this range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="cg-actions">
      <button type="submit" class="button btn disable">Save Content Groups Settings</button>
      <button type="button" class="button" data-content-groups-rebuild>Refresh / Rebuild Report</button>
      <button type="button" class="button" data-content-groups-reset>Reset To Defaults</button>
    </div>
    <div class="cg-flash" data-content-groups-flash></div>
  </form>
</div>
<template id="content-group-row-template">
  <tr data-content-group-row>
    <td>
      <input type="hidden" data-field="id" value="">
      <input type="text" data-field="name" maxlength="80" value="">
    </td>
    <td>
      <select data-field="match_type">
        <?php foreach ($matchTypes as $type): ?>
          <option value="<?= $h($type) ?>"><?= $h(ucfirst($type)) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="text" data-field="rule" maxlength="255" value="" placeholder="/blog/*"></td>
    <td><input type="checkbox" data-field="active" value="1" checked></td>
    <td><button type="button" class="button danger" data-remove-content-group>Delete</button></td>
  </tr>
</template>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-content-groups-admin')) {
    root = document.querySelector('[data-content-groups-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-content-groups-form]');
  var flash = root.querySelector('[data-content-groups-flash]');
  var body = root.querySelector('[data-content-groups-body]');
  var addBtn = root.querySelector('[data-add-content-group]');
  var resetBtn = root.querySelector('[data-content-groups-reset]');
  var rebuildBtn = root.querySelector('[data-content-groups-rebuild]');
  var template = document.getElementById('content-group-row-template');

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
    var rows = body.querySelectorAll('[data-content-group-row]');
    for (var i = 0; i < rows.length; i++) {
      var row = rows[i];
      var fields = row.querySelectorAll('[data-field], [name]');
      for (var j = 0; j < fields.length; j++) {
        var field = fields[j];
        var key = field.getAttribute('data-field');
        if (!key && field.name) {
          var match = field.name.match(/\]\[([a-z_]+)\]$/i);
          key = match ? match[1] : null;
        }
        if (!key) continue;
        field.name = 'groups[' + i + '][' + key + ']';
      }
    }
  }

  function attachRow(row){
    stopDragPropagation(row);
    var removeBtn = row.querySelector('[data-remove-content-group]');
    if (removeBtn) {
      stopDragPropagation(removeBtn);
      removeBtn.addEventListener('click', function(){
        row.remove();
        renumberRows();
      });
    }
  }

  if (body) {
    var existing = body.querySelectorAll('[data-content-group-row]');
    for (var i = 0; i < existing.length; i++) {
      attachRow(existing[i]);
    }
  }

  if (addBtn && body && template) {
    stopDragPropagation(addBtn);
    addBtn.addEventListener('click', function(){
      var frag = template.content.cloneNode(true);
      var row = frag.querySelector('[data-content-group-row]');
      if (!row) return;
      body.appendChild(row);
      renumberRows();
      attachRow(row);
    });
  }

  if (form) {
    stopDragPropagation(form);
    form.addEventListener('submit', function(e){
      e.preventDefault();
      renumberRows();
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
      if (!confirm('Reset Content Groups to defaults?')) return;
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
      setFlash('Refreshing report...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-rebuild-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash('Refresh failed.', true);
          return;
        }
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }
})();
</script>
