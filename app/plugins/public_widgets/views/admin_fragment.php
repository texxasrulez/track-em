<?php
declare(strict_types=1);

$counter = $config['counter'] ?? [];
$map = $config['map'] ?? [];
$profile = $map['profile'] ?? [];

$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
?>
<style>
  .pw-admin {
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 92%, transparent);
  }
  .pw-admin h5 {
    margin: 0 0 6px;
    font-size: 15px;
  }
  .pw-admin p,
  .pw-admin label,
  .pw-admin .note {
    font-size: 13px;
  }
  .pw-admin .pw-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 10px 12px;
    margin: 10px 0 12px;
  }
  .pw-admin .pw-grid label {
    display: block;
  }
  .pw-admin textarea {
    width: 100%;
    min-height: 78px;
    resize: vertical;
    user-select: text;
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 12px;
    background: var(--muted);
    color: var(--text);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 8px;
  }
  .pw-admin input[type="text"],
  .pw-admin input[type="number"],
  .pw-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .pw-admin .pw-section + .pw-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .pw-admin .pw-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 12px;
    align-items: center;
  }
  .pw-admin .pw-flash {
    margin-top: 10px;
    font-size: 13px;
    min-height: 18px;
  }
  .pw-admin .pw-snippet-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 8px;
  }
  .pw-admin .pw-copy-btn {
    min-width: 110px;
  }
  .pw-admin .pw-actions .button,
  .pw-admin .pw-actions button,
  .pw-admin .pw-snippet-actions .button,
  .pw-admin .pw-snippet-actions button {
    width: auto;
    flex: 0 0 auto;
  }
</style>
<div class="pw-admin" data-public-widgets-admin>
  <form data-public-widgets-form action="<?= $h(
      $this->service->routeUrl('public_widgets.save')
  ) ?>" data-reset-url="<?= $h(
    $this->service->routeUrl('public_widgets.reset')
) ?>">
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="note">
      Public Widgets only exposes aggregated, sanitized data. It never publishes IPs, visit ids, exact timestamps, referrers, user agents, or individual visitor coordinates.
    </div>

    <section class="pw-section">
      <h5>Hit Counter</h5>
      <div class="pw-grid">
        <label><input type="checkbox" name="counter_enabled" value="1" <?= !empty(
            $counter['enabled']
        )
            ? 'checked'
            : '' ?>> Enable public counter</label>
        <label>Counter mode
          <select name="counter_mode">
            <option value="site" <?= ($counter['mode'] ?? 'site') === 'site'
                ? 'selected'
                : '' ?>>Site-wide</option>
            <option value="path" <?= ($counter['mode'] ?? '') === 'path'
                ? 'selected'
                : '' ?>>Per page/path</option>
          </select>
        </label>
        <label>Time range
          <select name="counter_range">
            <option value="all" <?= ($counter['range'] ?? '') === 'all'
                ? 'selected'
                : '' ?>>All time</option>
            <option value="today" <?= ($counter['range'] ?? '') === 'today'
                ? 'selected'
                : '' ?>>Today</option>
            <option value="7d" <?= ($counter['range'] ?? '') === '7d'
                ? 'selected'
                : '' ?>>Last 7 days</option>
            <option value="30d" <?= ($counter['range'] ?? '30d') === '30d'
                ? 'selected'
                : '' ?>>Last 30 days</option>
          </select>
        </label>
        <label>Display format
          <select name="counter_format">
            <option value="exact" <?= ($counter['format'] ?? '') === 'exact'
                ? 'selected'
                : '' ?>>Exact</option>
            <option value="compact" <?= ($counter['format'] ?? 'compact') === 'compact'
                ? 'selected'
                : '' ?>>Compact</option>
            <option value="rounded" <?= ($counter['format'] ?? '') === 'rounded'
                ? 'selected'
                : '' ?>>Rounded</option>
          </select>
        </label>
        <label>Label text
          <input type="text" name="counter_label" maxlength="48" value="<?= $h(
              $counter['label'] ?? 'Visits'
          ) ?>">
        </label>
      </div>
      <label>Embed snippet
        <textarea readonly data-counter-snippet><?= $h(
            $counterSnippet
        ) ?></textarea>
      </label>
      <div class="pw-snippet-actions">
        <button type="button" class="button pw-copy-btn" data-copy-target="counter">Copy Snippet</button>
      </div>
    </section>

    <section class="pw-section">
      <h5>Public Map</h5>
      <div class="pw-grid">
        <label><input type="checkbox" name="map_enabled" value="1" <?= !empty(
            $map['enabled']
        )
            ? 'checked'
            : '' ?>> Enable public map</label>
        <label>Profile id
          <input type="text" name="map_profile_id" maxlength="48" value="<?= $h(
              $profile['id'] ?? 'main'
          ) ?>">
        </label>
        <label>Map title
          <input type="text" name="map_title" maxlength="80" value="<?= $h(
              $profile['title'] ?? 'Visitor Map'
          ) ?>">
        </label>
        <label>Time range
          <select name="map_range">
            <option value="today" <?= ($profile['range'] ?? '') === 'today'
                ? 'selected'
                : '' ?>>Today</option>
            <option value="7d" <?= ($profile['range'] ?? '') === '7d'
                ? 'selected'
                : '' ?>>Last 7 days</option>
            <option value="30d" <?= ($profile['range'] ?? '30d') === '30d'
                ? 'selected'
                : '' ?>>Last 30 days</option>
            <option value="all" <?= ($profile['range'] ?? '') === 'all'
                ? 'selected'
                : '' ?>>All time</option>
          </select>
        </label>
        <label>Max points / buckets
          <input type="number" name="map_max_points" min="10" max="500" value="<?= $h(
              $profile['max_points'] ?? 120
          ) ?>">
        </label>
        <label>Privacy mode
          <select name="map_privacy_mode">
            <option value="country" <?= ($profile['privacy_mode'] ?? '') === 'country'
                ? 'selected'
                : '' ?>>Country/region-level</option>
            <option value="rounded" <?= ($profile['privacy_mode'] ?? '') === 'rounded'
                ? 'selected'
                : '' ?>>Rounded coordinates</option>
            <option value="bucketed" <?= ($profile['privacy_mode'] ?? 'bucketed') === 'bucketed'
                ? 'selected'
                : '' ?>>Clustered/bucketed</option>
          </select>
        </label>
        <label>Default map view
          <select name="map_tile_layer">
            <?php foreach ($basemapOptions as $key => $label): ?>
              <option value="<?= $h($key) ?>" <?= ($profile['tile_layer'] ?? 'roads') === $key
                  ? 'selected'
                  : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Coordinate precision
          <input type="number" name="map_coordinate_precision" min="0" max="3" value="<?= $h(
              $profile['coordinate_precision'] ?? 1
          ) ?>">
        </label>
        <label>Bucket size (degrees)
          <input type="number" name="map_bucket_size_deg" min="0.1" max="15" step="0.1" value="<?= $h(
              $profile['bucket_size_deg'] ?? 2.5
          ) ?>">
        </label>
        <label>Coordinate jitter
          <input type="number" name="map_jitter" min="0" max="1" step="0.01" value="<?= $h(
              $profile['jitter'] ?? 0.18
          ) ?>">
        </label>
        <label>Minimum bucket size
          <input type="number" name="map_min_bucket_size" min="3" max="100" value="<?= $h(
              $profile['min_bucket_size'] ?? 3
          ) ?>">
        </label>
        <label>Map height suggestion
          <input type="number" name="map_height" min="240" max="1200" value="<?= $h(
              $profile['height'] ?? 520
          ) ?>">
        </label>
        <label><input type="checkbox" name="map_show_counts" value="1" <?= !empty(
            $profile['show_counts']
        )
            ? 'checked'
            : '' ?>> Show aggregate counts on dots</label>
      </div>
      <label>Iframe snippet
        <textarea readonly data-map-snippet><?= $h($mapSnippet) ?></textarea>
      </label>
      <div class="pw-snippet-actions">
        <button type="button" class="button pw-copy-btn" data-copy-target="map">Copy Snippet</button>
      </div>
    </section>

    <div class="pw-actions">
      <button type="submit" class="button btn disable">Save Widget Settings</button>
      <button type="button" class="button" data-public-widgets-reset>Reset To Defaults</button>
    </div>
    <div class="pw-flash" data-public-widgets-flash></div>
  </form>
</div>
<script>
(function(){
  var root = document.currentScript && document.currentScript.previousElementSibling;
  if (!root || !root.hasAttribute('data-public-widgets-admin')) {
    root = document.querySelector('[data-public-widgets-admin]:last-of-type');
  }
  if (!root) return;

  var form = root.querySelector('[data-public-widgets-form]');
  var flash = root.querySelector('[data-public-widgets-flash]');
  var counterSnippet = root.querySelector('[data-counter-snippet]');
  var mapSnippet = root.querySelector('[data-map-snippet]');
  var resetBtn = root.querySelector('[data-public-widgets-reset]');
  var copyButtons = root.querySelectorAll('[data-copy-target]');

  function setFlash(msg, isError){
    if (!flash) return;
    flash.textContent = msg || '';
    flash.style.color = isError ? '#c2410c' : 'var(--text)';
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
    if (counterSnippet && typeof data.counter_snippet === 'string') {
      counterSnippet.value = data.counter_snippet;
    }
    if (mapSnippet && typeof data.map_snippet === 'string') {
      mapSnippet.value = data.map_snippet;
    }
    setFlash('Settings saved.', false);
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

  [counterSnippet, mapSnippet].forEach(function(el){
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
          var field = target === 'map' ? mapSnippet : counterSnippet;
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

  if (resetBtn && form) {
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Public Widgets to privacy-preserving defaults?')) return;
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
          var pick = function(value, fallback){
            return typeof value === 'undefined' || value === null ? fallback : value;
          };
          var setValue = function(name, value){
            var el = form.querySelector('[name="' + name + '"]');
            if (!el) return;
            if (el.type === 'checkbox') {
              el.checked = !!value;
            } else {
              el.value = value;
            }
          };
          setValue('counter_enabled', cfg.counter && cfg.counter.enabled);
          setValue('counter_mode', pick(cfg.counter && cfg.counter.mode, 'site'));
          setValue('counter_range', pick(cfg.counter && cfg.counter.range, '30d'));
          setValue('counter_format', pick(cfg.counter && cfg.counter.format, 'compact'));
          setValue('counter_label', pick(cfg.counter && cfg.counter.label, 'Visits'));
          setValue('map_enabled', cfg.map && cfg.map.enabled);
          setValue('map_profile_id', pick(cfg.map && cfg.map.profile && cfg.map.profile.id, 'main'));
          setValue('map_title', pick(cfg.map && cfg.map.profile && cfg.map.profile.title, 'Visitor Map'));
          setValue('map_range', pick(cfg.map && cfg.map.profile && cfg.map.profile.range, '30d'));
          setValue('map_max_points', pick(cfg.map && cfg.map.profile && cfg.map.profile.max_points, 120));
          setValue('map_privacy_mode', pick(cfg.map && cfg.map.profile && cfg.map.profile.privacy_mode, 'bucketed'));
          setValue('map_tile_layer', pick(cfg.map && cfg.map.profile && cfg.map.profile.tile_layer, 'roads'));
          setValue('map_coordinate_precision', pick(cfg.map && cfg.map.profile && cfg.map.profile.coordinate_precision, 1));
          setValue('map_bucket_size_deg', pick(cfg.map && cfg.map.profile && cfg.map.profile.bucket_size_deg, 2.5));
          setValue('map_jitter', pick(cfg.map && cfg.map.profile && cfg.map.profile.jitter, 0.18));
          setValue('map_min_bucket_size', pick(cfg.map && cfg.map.profile && cfg.map.profile.min_bucket_size, 3));
          setValue('map_height', pick(cfg.map && cfg.map.profile && cfg.map.profile.height, 520));
          setValue('map_show_counts', cfg.map && cfg.map.profile && cfg.map.profile.show_counts);
        }
        applyResponse(data);
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }
})();
</script>
