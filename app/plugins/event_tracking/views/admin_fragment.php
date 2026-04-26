<?php
declare(strict_types=1);

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
$allowedNames = implode("\n", $config['allowed_event_names'] ?? []);
?>
<style>
  .et-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .et-admin .et-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .et-admin .et-grid label {
    display: block;
    font-size: 13px;
  }
  .et-admin textarea {
    width: 100%;
    min-height: 84px;
    resize: vertical;
    user-select: text;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    background: var(--muted);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px;
    box-sizing: border-box;
  }
  .et-admin input[type="text"],
  .et-admin input[type="number"],
  .et-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .et-admin .et-section + .et-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .et-admin .et-actions,
  .et-admin .et-snippet-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
  }
  .et-admin .et-snippet-actions {
    justify-content: flex-end;
    margin-top: 8px;
  }
  .et-admin .et-report-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 10px;
    margin: 10px 0 14px;
  }
  .et-admin .et-stat {
    padding: 12px;
    border: 1px solid var(--border);
    border-radius: 10px;
    background: rgba(255,255,255,0.45);
  }
  .et-admin .et-stat strong {
    display: block;
    font-size: 22px;
  }
  .et-admin table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 8px;
  }
  .et-admin th,
  .et-admin td {
    text-align: left;
    padding: 6px 8px;
    border-bottom: 1px solid var(--border);
    font-size: 13px;
    vertical-align: top;
  }
  .et-admin .et-note,
  .et-admin .et-flash {
    font-size: 13px;
  }
  .et-admin .et-flash {
    min-height: 18px;
    margin-top: 10px;
  }
  .et-admin .et-actions .button,
  .et-admin .et-actions button,
  .et-admin .et-snippet-actions .button,
  .et-admin .et-snippet-actions button {
    width: auto;
    flex: 0 0 auto;
  }
</style>
<div class="et-admin" data-event-tracking-admin>
  <form data-event-tracking-form action="<?= $h(
      $this->service->routeUrl('event_tracking.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('event_tracking.reset')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="et-note">
      This plugin tracks simple named events only. It does not collect form values, passwords, textarea contents, or browser fingerprint data.
    </div>

    <section class="et-section">
      <h5>Collection Settings</h5>
      <div class="et-grid">
        <label><input type="checkbox" name="enabled" value="1" <?= !empty(
            $config['enabled']
        )
            ? 'checked'
            : '' ?>> Accept event collection</label>
        <label>Event name rules
          <select name="validation_rule">
            <option value="strict" <?= ($config['validation_rule'] ?? '') === 'strict'
                ? 'selected'
                : '' ?>>Lowercase, digits, underscore</option>
            <option value="extended" <?= ($config['validation_rule'] ?? 'extended') === 'extended'
                ? 'selected'
                : '' ?>>Lowercase, digits, `_ . : -`</option>
          </select>
        </label>
        <label>Retention period (days)
          <input type="number" name="retention_days" min="1" max="3650" value="<?= $h(
              $config['retention_days'] ?? 90
          ) ?>">
        </label>
        <label>Max event name length
          <input type="number" name="max_event_name_length" min="8" max="128" value="<?= $h(
              $config['max_event_name_length'] ?? 64
          ) ?>">
        </label>
        <label>Max metadata keys
          <input type="number" name="max_metadata_keys" min="0" max="20" value="<?= $h(
              $config['max_metadata_keys'] ?? 5
          ) ?>">
        </label>
        <label>Max metadata value length
          <input type="number" name="max_metadata_value_length" min="10" max="500" value="<?= $h(
              $config['max_metadata_value_length'] ?? 100
          ) ?>">
        </label>
      </div>
      <label>Allowed event names
        <textarea name="allowed_event_names" placeholder="Optional. One event per line. Leave empty to allow any valid name."><?= $h(
            $allowedNames
        ) ?></textarea>
      </label>
    </section>

    <section class="et-section">
      <h5>Embed Snippets</h5>
      <label>JavaScript API snippet
        <textarea readonly data-script-snippet><?= $h($scriptSnippet) ?></textarea>
      </label>
      <div class="et-snippet-actions">
        <button type="button" class="button" data-copy-target="script">Copy Snippet</button>
      </div>

      <label style="margin-top:12px">Declarative HTML example
        <textarea readonly data-declarative-snippet><?= $h(
            $declarativeSnippet
        ) ?></textarea>
      </label>
      <div class="et-snippet-actions">
        <button type="button" class="button" data-copy-target="declarative">Copy Snippet</button>
      </div>
    </section>

    <section class="et-section">
      <h5>Reporting</h5>
      <div class="et-report-grid">
        <div class="et-stat"><span>Today</span><strong><?= (int) ($report['totals']['today'] ?? 0) ?></strong></div>
        <div class="et-stat"><span>Last 7 days</span><strong><?= (int) ($report['totals']['last_7_days'] ?? 0) ?></strong></div>
        <div class="et-stat"><span>Last 30 days</span><strong><?= (int) ($report['totals']['last_30_days'] ?? 0) ?></strong></div>
      </div>

      <h6>Top Event Names</h6>
      <table>
        <thead><tr><th>Event</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_events'])): ?>
            <?php foreach ($report['top_events'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No events yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Top Labels</h6>
      <table>
        <thead><tr><th>Label</th><th>Count</th></tr></thead>
        <tbody>
          <?php if (!empty($report['top_labels'])): ?>
            <?php foreach ($report['top_labels'] as $row): ?>
              <tr><td><?= $h($row['name']) ?></td><td><?= (int) $row['count'] ?></td></tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="2">No labels yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <h6 style="margin-top:12px">Recent Events</h6>
      <table>
        <thead><tr><th>When</th><th>Event</th><th>Label</th><th>Path</th><th>Meta</th></tr></thead>
        <tbody>
          <?php if (!empty($report['recent'])): ?>
            <?php foreach ($report['recent'] as $row): ?>
              <tr>
                <td><?= $h(date('Y-m-d H:i:s', (int) $row['ts'])) ?></td>
                <td><?= $h($row['event']) ?></td>
                <td><?= $h($row['label']) ?></td>
                <td><?= $h($row['path']) ?></td>
                <td><code><?= $h(
                    json_encode($row['meta'], JSON_UNESCAPED_SLASHES)
                ) ?></code></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr><td colspan="5">No recent events.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </section>

    <div class="et-actions" style="margin-top:12px">
      <button type="submit" class="button btn disable">Save Event Tracking Settings</button>
      <button type="button" class="button" data-event-tracking-reset>Reset To Defaults</button>
    </div>
    <div class="et-flash" data-event-tracking-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-event-tracking-admin')) {
    root = document.querySelector('[data-event-tracking-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-event-tracking-form]');
  var flash = root.querySelector('[data-event-tracking-flash]');
  var scriptSnippet = root.querySelector('[data-script-snippet]');
  var declarativeSnippet = root.querySelector('[data-declarative-snippet]');
  var resetBtn = root.querySelector('[data-event-tracking-reset]');
  var copyButtons = root.querySelectorAll('[data-copy-target]');

  function setFlash(msg, isError){
    if (!flash) return;
    flash.textContent = msg || '';
    flash.style.color = isError ? '#c2410c' : 'var(--text)';
  }

  function stopDragPropagation(el){
    if (!el) return;
    ['mousedown', 'pointerdown', 'dragstart', 'touchstart'].forEach(function(type){
      el.addEventListener(type, function(e){
        e.stopPropagation();
      });
    });
  }

  function copyText(text){
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    return new Promise(function(resolve, reject){
      var helper = document.createElement('textarea');
      helper.value = text;
      helper.setAttribute('readonly', 'readonly');
      helper.style.position = 'fixed';
      helper.style.opacity = '0';
      document.body.appendChild(helper);
      helper.focus();
      helper.select();
      try {
        document.execCommand('copy');
        document.body.removeChild(helper);
        resolve();
      } catch (err) {
        document.body.removeChild(helper);
        reject(err);
      }
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

  function applyResponse(data){
    if (!data || data.ok !== true) {
      setFlash('Save failed.', true);
      return;
    }
    if (scriptSnippet && typeof data.script_snippet === 'string') {
      scriptSnippet.value = data.script_snippet;
    }
    if (declarativeSnippet && typeof data.declarative_snippet === 'string') {
      declarativeSnippet.value = data.declarative_snippet;
    }
    setFlash('Settings saved. Reload the Plugins page to refresh reports.', false);
  }

  [scriptSnippet, declarativeSnippet].forEach(function(el){
    if (!el) return;
    stopDragPropagation(el);
    el.setAttribute('draggable', 'false');
  });

  if (copyButtons && copyButtons.length) {
    for (var i = 0; i < copyButtons.length; i++) {
      (function(btn){
        stopDragPropagation(btn);
        btn.addEventListener('click', function(){
          var target = btn.getAttribute('data-copy-target');
          var field = target === 'declarative' ? declarativeSnippet : scriptSnippet;
          if (!field) return;
          copyText(field.value || '').then(function(){
            try { field.focus(); field.select(); } catch (err) {}
            setFlash('Snippet copied.', false);
          }).catch(function(){
            setFlash('Copy failed. Select the snippet and copy it manually.', true);
          });
        });
      })(copyButtons[i]);
    }
  }

  if (form) {
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
        if (xhr.status !== 200) {
          setFlash((data && data.error) ? data.error : 'Save failed.', true);
          return;
        }
        applyResponse(data);
      };
      xhr.send(encodeFormData(form));
    });
  }

  if (resetBtn && form) {
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Event Tracking to defaults?')) return;
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
        if (data.config) {
          var cfg = data.config;
          var setValue = function(name, value){
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            if (el.type === 'checkbox') {
              el.checked = !!value;
            } else if (Array.isArray(value)) {
              el.value = value.join('\n');
            } else {
              el.value = value;
            }
          };
          setValue('enabled', cfg.enabled);
          setValue('allowed_event_names', cfg.allowed_event_names || []);
          setValue('validation_rule', cfg.validation_rule || 'extended');
          setValue('retention_days', cfg.retention_days || 90);
          setValue('max_event_name_length', cfg.max_event_name_length || 64);
          setValue('max_metadata_keys', cfg.max_metadata_keys || 5);
          setValue('max_metadata_value_length', cfg.max_metadata_value_length || 100);
        }
        applyResponse(data);
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }
})();
</script>
