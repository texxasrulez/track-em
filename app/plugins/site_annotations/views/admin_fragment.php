<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$annotations = is_array($config['annotations'] ?? null) ? $config['annotations'] : [];
?>
<style>
  .sa-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .sa-admin .sa-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .sa-admin .sa-grid label {
    display: block;
    font-size: 13px;
  }
  .sa-admin input[type="text"],
  .sa-admin input[type="date"],
  .sa-admin input[type="number"],
  .sa-admin select,
  .sa-admin textarea {
    width: 100%;
    box-sizing: border-box;
  }
  .sa-admin textarea {
    min-height: 74px;
    resize: vertical;
  }
  .sa-admin .sa-section + .sa-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .sa-admin .sa-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    align-items: center;
  }
  .sa-admin .sa-actions .button,
  .sa-admin .sa-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .sa-admin .sa-note,
  .sa-admin .sa-flash {
    font-size: 13px;
  }
  .sa-admin .sa-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .sa-admin .sa-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .sa-admin .sa-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .sa-admin .sa-stat strong {
    display: block;
    font-size: 22px;
  }
  .sa-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
  }
  .sa-admin th,
  .sa-admin td {
    text-align: left;
    vertical-align: top;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
  }
  .sa-admin .sa-tag {
    display: inline-block;
    border: 1px solid var(--border);
    border-radius: 999px;
    padding: 2px 8px;
    font-size: 12px;
  }
</style>
<div class="sa-admin" data-site-annotations-admin>
  <form data-site-annotations-form action="<?= $h(
      $this->service->routeUrl('site_annotations.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('site_annotations.reset')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="sa-note">
      Site Annotations is a lightweight admin timeline for deploys, campaigns, outages, content launches, and general notes. It stores only sanitized annotation text and does not expose public data.
    </div>

    <section class="sa-section">
      <h5>Settings</h5>
      <div class="sa-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty(
            $config['enabled']
        )
            ? 'checked'
            : '' ?>> Enable site annotations</label>
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
        <label>Default type
          <select name="default_type" data-site-annotations-default-type>
            <?php foreach ($annotationTypes as $type): ?>
              <option value="<?= $h($type) ?>" <?= ($config['default_type'] ?? 'note') === $type
                  ? 'selected'
                  : '' ?>><?= $h(ucfirst($type)) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Max stored annotations
          <input type="number" name="max_annotations" min="25" max="1000" value="<?= $h(
              $config['max_annotations'] ?? 250
          ) ?>">
        </label>
      </div>
    </section>

    <section class="sa-section">
      <h5>Annotations</h5>
      <table data-site-annotations-table>
        <thead>
          <tr>
            <th>Date</th>
            <th>Type</th>
            <th>Title</th>
            <th>Note</th>
            <th>Path</th>
            <th>Active</th>
            <th></th>
          </tr>
        </thead>
        <tbody data-site-annotations-body>
          <?php foreach ($annotations as $index => $annotation): ?>
            <tr data-site-annotation-row>
              <td>
                <input type="hidden" name="annotations[<?= (int) $index ?>][id]" value="<?= $h(
                    $annotation['id'] ?? ''
                ) ?>">
                <input type="date" name="annotations[<?= (int) $index ?>][date]" value="<?= $h(
                    $annotation['date'] ?? ''
                ) ?>">
              </td>
              <td>
                <select name="annotations[<?= (int) $index ?>][type]">
                  <?php foreach ($annotationTypes as $type): ?>
                    <option value="<?= $h($type) ?>" <?= ($annotation['type'] ?? 'note') === $type
                        ? 'selected'
                        : '' ?>><?= $h(ucfirst($type)) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td><input type="text" name="annotations[<?= (int) $index ?>][title]" maxlength="120" value="<?= $h(
                  $annotation['title'] ?? ''
              ) ?>"></td>
              <td><textarea name="annotations[<?= (int) $index ?>][note]" maxlength="400"><?= $h(
                  $annotation['note'] ?? ''
              ) ?></textarea></td>
              <td><input type="text" name="annotations[<?= (int) $index ?>][path]" maxlength="255" value="<?= $h(
                  $annotation['path'] ?? ''
              ) ?>" placeholder="/pricing"></td>
              <td><input type="checkbox" name="annotations[<?= (int) $index ?>][active]" value="1" <?= !empty(
                  $annotation['active']
              )
                  ? 'checked'
                  : '' ?>></td>
              <td><button type="button" class="button danger" data-remove-site-annotation>Delete</button></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="sa-actions">
        <button type="button" class="button" data-add-site-annotation>Add Annotation</button>
      </div>
    </section>

    <section class="sa-section">
      <h5>Reporting</h5>
      <div class="sa-stats">
        <div class="sa-stat"><span>Total In Range</span><strong><?= (int) ($report['summary']['total'] ?? 0) ?></strong></div>
        <div class="sa-stat"><span>Active</span><strong><?= (int) ($report['summary']['active'] ?? 0) ?></strong></div>
        <div class="sa-stat"><span>Upcoming</span><strong><?= (int) ($report['summary']['upcoming'] ?? 0) ?></strong></div>
      </div>

      <?php if (!empty($report['notes'])): ?>
        <?php foreach ($report['notes'] as $note): ?>
          <div class="sa-note"><?= $h($note) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <h6>Types</h6>
      <table>
        <thead><tr><th>Type</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['types'])): ?>
            <?php foreach ($report['types'] as $row): ?>
              <tr><td><?= $h(ucfirst($row['name'])) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No annotations in this range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Recent Annotations</h6>
      <table>
        <thead><tr><th>Date</th><th>Type</th><th>Title</th><th>Details</th></tr></thead>
        <tbody>
          <?php if (!empty($report['recent'])): ?>
            <?php foreach ($report['recent'] as $annotation): ?>
              <tr>
                <td><?= $h($annotation['date'] ?? '') ?></td>
                <td><span class="sa-tag"><?= $h(ucfirst((string) ($annotation['type'] ?? 'note'))) ?></span></td>
                <td><?= $h($annotation['title'] ?? '') ?></td>
                <td>
                  <?= $h($annotation['note'] ?? '') ?>
                  <?php if (!empty($annotation['path'])): ?>
                    <div class="sa-note">Path: <?= $h($annotation['path']) ?></div>
                  <?php endif; ?>
                  <?php if (empty($annotation['active'])): ?>
                    <div class="sa-note">Inactive</div>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="4">No annotations in this range.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="sa-actions">
      <button type="submit" class="button btn disable">Save Site Annotations Settings</button>
      <button type="button" class="button" data-site-annotations-reset>Reset To Defaults</button>
    </div>
    <div class="sa-flash" data-site-annotations-flash></div>
  </form>
</div>
<template id="site-annotation-row-template">
  <tr data-site-annotation-row>
    <td>
      <input type="hidden" data-field="id" value="">
      <input type="date" data-field="date" value="">
    </td>
    <td>
      <select data-field="type">
        <?php foreach ($annotationTypes as $type): ?>
          <option value="<?= $h($type) ?>"><?= $h(ucfirst($type)) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="text" data-field="title" maxlength="120" value=""></td>
    <td><textarea data-field="note" maxlength="400"></textarea></td>
    <td><input type="text" data-field="path" maxlength="255" value="" placeholder="/pricing"></td>
    <td><input type="checkbox" data-field="active" value="1" checked></td>
    <td><button type="button" class="button danger" data-remove-site-annotation>Delete</button></td>
  </tr>
</template>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-site-annotations-admin')) {
    root = document.querySelector('[data-site-annotations-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-site-annotations-form]');
  var flash = root.querySelector('[data-site-annotations-flash]');
  var body = root.querySelector('[data-site-annotations-body]');
  var addBtn = root.querySelector('[data-add-site-annotation]');
  var resetBtn = root.querySelector('[data-site-annotations-reset]');
  var template = document.getElementById('site-annotation-row-template');
  var defaultType = root.querySelector('[data-site-annotations-default-type]');

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
    var rows = body.querySelectorAll('[data-site-annotation-row]');
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
        field.name = 'annotations[' + i + '][' + key + ']';
      }
    }
  }

  function attachRow(row){
    stopDragPropagation(row);
    var removeBtn = row.querySelector('[data-remove-site-annotation]');
    if (removeBtn) {
      stopDragPropagation(removeBtn);
      removeBtn.addEventListener('click', function(){
        row.remove();
        renumberRows();
      });
    }
  }

  if (body) {
    var existing = body.querySelectorAll('[data-site-annotation-row]');
    for (var i = 0; i < existing.length; i++) {
      attachRow(existing[i]);
    }
  }

  if (addBtn && body && template) {
    stopDragPropagation(addBtn);
    addBtn.addEventListener('click', function(){
      var frag = template.content.cloneNode(true);
      var row = frag.querySelector('[data-site-annotation-row]');
      if (!row) return;
      var today = new Date().toISOString().slice(0, 10);
      var dateField = row.querySelector('[data-field="date"]');
      var typeField = row.querySelector('[data-field="type"]');
      var activeField = row.querySelector('[data-field="active"]');
      if (dateField) dateField.value = today;
      if (typeField && defaultType) typeField.value = defaultType.value || 'note';
      if (activeField) activeField.checked = true;
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
      if (!confirm('Reset Site Annotations to defaults?')) return;
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
