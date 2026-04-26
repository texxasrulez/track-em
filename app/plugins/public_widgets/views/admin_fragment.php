<?php
declare(strict_types=1);

$counter = $config['counter'] ?? [];
$map = $config['map'] ?? [];
$profile = $map['profile'] ?? [];
$selectedDigitTheme = (string) ($counterPreview['digit_theme'] ?? 'default');
$selectedThemeMeta = null;
foreach ((array) $digitThemes as $theme) {
    if (($theme['id'] ?? '') === $selectedDigitTheme) {
        $selectedThemeMeta = $theme;
        break;
    }
}

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
  .pw-admin input[type="file"],
  .pw-admin select {
    width: 100%;
    box-sizing: border-box;
  }
  .pw-admin .pw-section + .pw-section {
    margin-top: 16px;
    padding-top: 14px;
    border-top: 1px solid var(--border);
  }
  .pw-admin .pw-actions,
  .pw-admin .pw-inline-actions {
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
  .pw-admin .pw-snippet-actions button,
  .pw-admin .pw-inline-actions .button,
  .pw-admin .pw-inline-actions button {
    width: auto;
    flex: 0 0 auto;
  }
  .pw-admin .pw-preview {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 2px;
    min-height: 36px;
    padding: 8px;
    border: 1px dashed var(--border);
    border-radius: 8px;
    background: rgba(255,255,255,0.4);
  }
  .pw-admin .pw-preview img {
    display: inline-block;
    vertical-align: middle;
    width: auto;
  }
  .pw-admin .pw-preview-char {
    display: inline-block;
    font: 600 16px/1 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    padding: 0 1px;
  }
  .pw-admin .pw-help-list {
    margin: 8px 0 0;
    padding-left: 18px;
  }
  .pw-admin .pw-theme-meta {
    font-size: 12px;
    opacity: 0.8;
  }
</style>
<div class="pw-admin" data-public-widgets-admin>
  <form
    data-public-widgets-form
    action="<?= $h($this->service->routeUrl('public_widgets.save')) ?>"
    data-reset-url="<?= $h($this->service->routeUrl('public_widgets.reset')) ?>"
    data-upload-url="<?= $h($this->service->routeUrl('public_widgets.upload_theme')) ?>"
    data-delete-url="<?= $h($this->service->routeUrl('public_widgets.delete_theme')) ?>"
  >
    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">

    <div class="note">
      Public Widgets only exposes aggregated, sanitized data. It never publishes IPs, visit ids, exact timestamps, referrers, user agents, or individual visitor coordinates.
    </div>

    <section class="pw-section">
      <h5>Hit Counter</h5>
      <div class="pw-grid">
        <label><input type="checkbox" name="counter_enabled" value="1" <?= !empty($counter['enabled']) ? 'checked' : '' ?>> Enable public counter</label>
        <label>Counter mode
          <select name="counter_mode">
            <option value="site" <?= ($counter['mode'] ?? 'site') === 'site' ? 'selected' : '' ?>>Site-wide</option>
            <option value="path" <?= ($counter['mode'] ?? '') === 'path' ? 'selected' : '' ?>>Per page/path</option>
          </select>
        </label>
        <label>Time range
          <select name="counter_range">
            <option value="all" <?= ($counter['range'] ?? '') === 'all' ? 'selected' : '' ?>>All time</option>
            <option value="today" <?= ($counter['range'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="7d" <?= ($counter['range'] ?? '') === '7d' ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30d" <?= ($counter['range'] ?? '30d') === '30d' ? 'selected' : '' ?>>Last 30 days</option>
          </select>
        </label>
        <label>Counter value format
          <select name="counter_format">
            <option value="exact" <?= ($counter['format'] ?? '') === 'exact' ? 'selected' : '' ?>>Exact</option>
            <option value="compact" <?= ($counter['format'] ?? 'compact') === 'compact' ? 'selected' : '' ?>>Compact</option>
            <option value="rounded" <?= ($counter['format'] ?? '') === 'rounded' ? 'selected' : '' ?>>Rounded</option>
          </select>
        </label>
        <label>Counter display mode
          <select name="counter_display_mode" data-counter-display-mode>
            <option value="text" <?= ($counter['display_mode'] ?? 'text') === 'text' ? 'selected' : '' ?>>Text counter</option>
            <option value="image_digits" <?= ($counter['display_mode'] ?? '') === 'image_digits' ? 'selected' : '' ?>>Image digit counter</option>
          </select>
        </label>
        <label>Label text
          <input type="text" name="counter_label" maxlength="48" value="<?= $h($counter['label'] ?? 'Visits') ?>">
        </label>
      </div>

      <div data-counter-image-settings <?= ($counter['display_mode'] ?? 'text') === 'image_digits' ? '' : 'hidden' ?>>
        <div class="pw-grid">
          <label>Digit theme
            <select name="counter_digit_theme" data-digit-theme-select>
              <?php foreach ($digitThemes as $theme): ?>
                <option
                  value="<?= $h($theme['id'] ?? '') ?>"
                  data-source="<?= $h((string) ($theme['source'] ?? 'uploaded')) ?>"
                  data-deletable="<?= !empty($theme['deletable']) ? '1' : '0' ?>"
                  <?= ($theme['id'] ?? '') === $selectedDigitTheme ? 'selected' : '' ?>
                >
                  <?= $h($theme['name'] ?? '') ?> [<?= $h(($theme['source'] ?? '') === 'built_in' ? 'Built-in' : 'Uploaded') ?>]
                </option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Digit height (px)
            <input type="number" name="counter_digit_height" min="12" max="128" value="<?= $h($counter['digit_height'] ?? 24) ?>" data-digit-height-input>
          </label>
        </div>
        <div class="note">Preview</div>
        <div
          class="pw-preview"
          data-digit-preview
          data-preview-theme="<?= $h($selectedDigitTheme) ?>"
          data-preview-height="<?= $h((string) ($counter['digit_height'] ?? 24)) ?>"
          data-digit-url-base="<?= $h($this->service->routeUrl('public_widgets.digit')) ?>"
          aria-label="Digit theme preview 0 1 2 3 4 5 6 7 8 9"
        ></div>
        <?php if ($selectedThemeMeta): ?>
          <div class="pw-theme-meta">
            Selected theme: <?= $h($selectedThemeMeta['name'] ?? '') ?>.
            Source: <?= $h(($selectedThemeMeta['source'] ?? '') === 'built_in' ? 'Built-in' : 'Uploaded') ?>.
          </div>
        <?php endif; ?>

        <?php if ($zipUploadAvailable): ?>
          <div class="pw-inline-actions">
            <button type="button" class="button" data-show-upload-theme>Upload New Digit Theme</button>
            <button type="button" class="button danger" data-delete-theme <?= ($selectedThemeMeta && !empty($selectedThemeMeta['deletable'])) ? '' : 'disabled' ?>>Delete Uploaded Theme</button>
          </div>
          <div data-upload-theme-panel hidden>
            <div class="pw-grid">
              <label>Theme name
                <input type="text" name="digit_theme_name" maxlength="80" placeholder="Blue Digital">
              </label>
              <label>Digit theme ZIP
                <input type="file" name="digit_theme_zip" accept=".zip,application/zip">
              </label>
            </div>
            <div class="note">
              ZIP must contain exactly <code>0.png</code> through <code>9.png</code> at the ZIP root. No folders, no extra files, no SVG.
            </div>
            <div class="pw-inline-actions">
              <button type="button" class="button" data-upload-theme>Upload Theme ZIP</button>
              <button type="button" class="button" data-hide-upload-theme>Cancel</button>
            </div>
          </div>
        <?php else: ?>
          <div class="note">ZIP upload is unavailable because PHP ZIP support is not installed on this server.</div>
        <?php endif; ?>
      </div>

      <div class="note">
        Counter snippets:
        Text mode keeps the current text counter behavior.
        Image digit mode renders digits as PNG images through a plugin-owned endpoint and falls back quietly if a theme cannot be resolved.
      </div>
      <label>Embed snippet
        <textarea readonly data-counter-snippet><?= $h($counterSnippet) ?></textarea>
      </label>
      <div class="pw-snippet-actions">
        <button type="button" class="button pw-copy-btn" data-copy-target="counter">Copy Snippet</button>
      </div>
      <div class="note">
        Digit ZIP guidance:
        <ul class="pw-help-list">
          <li>Required filenames: <code>0.png</code> through <code>9.png</code>.</li>
          <li>Use transparent PNGs when possible.</li>
          <li>Recommended size: around 24px to 64px tall.</li>
          <li>Maximum ZIP size is 2 MB. Maximum digit image size is 100 KB and 128x128 pixels.</li>
          <li>Nested paths, extra files, renamed non-images, and SVG files are rejected.</li>
        </ul>
      </div>
    </section>

    <section class="pw-section">
      <h5>Public Map</h5>
      <div class="pw-grid">
        <label><input type="checkbox" name="map_enabled" value="1" <?= !empty($map['enabled']) ? 'checked' : '' ?>> Enable public map</label>
        <label>Profile id
          <input type="text" name="map_profile_id" maxlength="48" value="<?= $h($profile['id'] ?? 'main') ?>">
        </label>
        <label>Map title
          <input type="text" name="map_title" maxlength="80" value="<?= $h($profile['title'] ?? 'Visitor Map') ?>">
        </label>
        <label>Time range
          <select name="map_range">
            <option value="today" <?= ($profile['range'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option>
            <option value="7d" <?= ($profile['range'] ?? '') === '7d' ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30d" <?= ($profile['range'] ?? '30d') === '30d' ? 'selected' : '' ?>>Last 30 days</option>
            <option value="all" <?= ($profile['range'] ?? '') === 'all' ? 'selected' : '' ?>>All time</option>
          </select>
        </label>
        <label>Max points / buckets
          <input type="number" name="map_max_points" min="10" max="500" value="<?= $h($profile['max_points'] ?? 120) ?>">
        </label>
        <label>Privacy mode
          <select name="map_privacy_mode">
            <option value="country" <?= ($profile['privacy_mode'] ?? '') === 'country' ? 'selected' : '' ?>>Country/region-level</option>
            <option value="rounded" <?= ($profile['privacy_mode'] ?? '') === 'rounded' ? 'selected' : '' ?>>Rounded coordinates</option>
            <option value="bucketed" <?= ($profile['privacy_mode'] ?? 'bucketed') === 'bucketed' ? 'selected' : '' ?>>Clustered/bucketed</option>
          </select>
        </label>
        <label>Default map view
          <select name="map_tile_layer">
            <?php foreach ($basemapOptions as $key => $label): ?>
              <option value="<?= $h($key) ?>" <?= ($profile['tile_layer'] ?? 'roads') === $key ? 'selected' : '' ?>><?= $h($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Coordinate precision
          <input type="number" name="map_coordinate_precision" min="0" max="3" value="<?= $h($profile['coordinate_precision'] ?? 1) ?>">
        </label>
        <label>Bucket size (degrees)
          <input type="number" name="map_bucket_size_deg" min="0.1" max="15" step="0.1" value="<?= $h($profile['bucket_size_deg'] ?? 2.5) ?>">
        </label>
        <label>Coordinate jitter
          <input type="number" name="map_jitter" min="0" max="1" step="0.01" value="<?= $h($profile['jitter'] ?? 0.18) ?>">
        </label>
        <label>Minimum bucket size
          <input type="number" name="map_min_bucket_size" min="3" max="100" value="<?= $h($profile['min_bucket_size'] ?? 3) ?>">
        </label>
        <label>Map height suggestion
          <input type="number" name="map_height" min="240" max="1200" value="<?= $h($profile['height'] ?? 520) ?>">
        </label>
        <label><input type="checkbox" name="map_show_counts" value="1" <?= !empty($profile['show_counts']) ? 'checked' : '' ?>> Show aggregate counts on dots</label>
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
  var displayModeSelect = root.querySelector('[data-counter-display-mode]');
  var imageSettings = root.querySelector('[data-counter-image-settings]');
  var themeSelect = root.querySelector('[data-digit-theme-select]');
  var preview = root.querySelector('[data-digit-preview]');
  var digitHeightInput = root.querySelector('[data-digit-height-input]');
  var showUploadBtn = root.querySelector('[data-show-upload-theme]');
  var hideUploadBtn = root.querySelector('[data-hide-upload-theme]');
  var uploadPanel = root.querySelector('[data-upload-theme-panel]');
  var uploadBtn = root.querySelector('[data-upload-theme]');
  var deleteBtn = root.querySelector('[data-delete-theme]');

  function updateCsrf(data){
    if (!data || typeof data.csrf !== 'string' || !form) return;
    var field = form.querySelector('[name="csrf"]');
    if (field) field.value = data.csrf;
  }

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
      if (el.type === 'file') continue;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
      pairs.push(encodeURIComponent(el.name) + '=' + encodeURIComponent(el.type === 'checkbox' ? '1' : el.value));
    }
    return pairs.join('&');
  }

  function applyResponse(data){
    updateCsrf(data);
    if (!data || data.ok !== true) {
      setFlash((data && (data.message || data.error)) ? (data.message || data.error) : 'Save failed.', true);
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

  function refreshDisplayMode(){
    if (!displayModeSelect || !imageSettings) return;
    imageSettings.hidden = displayModeSelect.value !== 'image_digits';
  }

  function renderPreview(){
    if (!preview) return;
    var themeId = (themeSelect && themeSelect.value) || preview.getAttribute('data-preview-theme') || 'default';
    var height = parseInt((digitHeightInput && digitHeightInput.value) || preview.getAttribute('data-preview-height') || '24', 10);
    var base = preview.getAttribute('data-digit-url-base') || '';
    preview.innerHTML = '';
    var digits = '0123456789';
    for (var i = 0; i < digits.length; i++) {
      var n = digits.charAt(i);
      var img = document.createElement('img');
      img.alt = '';
      img.src = base + '&id=' + encodeURIComponent(themeId) + '&n=' + encodeURIComponent(n);
      img.setAttribute('data-digit-char', n);
      img.style.height = String(Math.max(12, Math.min(128, isNaN(height) ? 24 : height))) + 'px';
      img.onerror = function(){
        var span = document.createElement('span');
        span.className = 'pw-preview-char';
        span.textContent = this.getAttribute('data-digit-char') || '?';
        this.replaceWith(span);
      };
      preview.appendChild(img);
    }
  }

  function refreshDeleteButton(){
    if (!deleteBtn || !themeSelect) return;
    var option = themeSelect.options[themeSelect.selectedIndex] || null;
    deleteBtn.disabled = !option || option.getAttribute('data-deletable') !== '1';
  }

  function refreshThemeUi(){
    refreshDisplayMode();
    renderPreview();
    refreshDeleteButton();
  }

  function refillThemes(themes, selectedId){
    if (!themeSelect || !themes || !themes.length) return;
    themeSelect.innerHTML = '';
    for (var i = 0; i < themes.length; i++) {
      var theme = themes[i];
      var opt = document.createElement('option');
      opt.value = String(theme.id || '');
      opt.textContent = String(theme.name || '') + ' [' + ((theme.source || '') === 'built_in' ? 'Built-in' : 'Uploaded') + ']';
      opt.setAttribute('data-source', String(theme.source || 'uploaded'));
      opt.setAttribute('data-deletable', theme.deletable ? '1' : '0');
      if (opt.value === selectedId) {
        opt.selected = true;
      }
      themeSelect.appendChild(opt);
    }
    refreshThemeUi();
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
        applyResponse(data);
      };
      xhr.send(encodeFormData(form));
    });
  }

  if (resetBtn && form) {
    resetBtn.addEventListener('click', function(){
      if (!confirm('Reset Public Widgets to defaults?')) return;
      setFlash('Resetting...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-reset-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        if (xhr.status !== 200) {
          setFlash('Reset failed.', true);
          return;
        }
        window.location.reload();
      };
      xhr.send('csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || ''));
    });
  }

  if (copyButtons) {
    for (var i = 0; i < copyButtons.length; i++) {
      (function(btn){
        btn.addEventListener('click', function(){
          var target = btn.getAttribute('data-copy-target');
          var field = target === 'map' ? mapSnippet : counterSnippet;
          if (!field) return;
          copyText(field.value || '').then(function(){
            setFlash('Snippet copied.', false);
          }).catch(function(){
            setFlash('Copy failed.', true);
          });
        });
      })(copyButtons[i]);
    }
  }

  if (showUploadBtn && uploadPanel) {
    showUploadBtn.addEventListener('click', function(){ uploadPanel.hidden = false; });
  }
  if (hideUploadBtn && uploadPanel) {
    hideUploadBtn.addEventListener('click', function(){ uploadPanel.hidden = true; });
  }
  if (uploadBtn && form) {
    uploadBtn.addEventListener('click', function(){
      var fd = new FormData();
      fd.append('csrf', form.querySelector('[name="csrf"]').value || '');
      var nameField = form.querySelector('[name="digit_theme_name"]');
      var fileField = form.querySelector('[name="digit_theme_zip"]');
      fd.append('digit_theme_name', nameField ? nameField.value : '');
      if (fileField && fileField.files && fileField.files[0]) {
        fd.append('digit_theme_zip', fileField.files[0]);
      }
      setFlash('Uploading theme...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-upload-url') || '', true);
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        updateCsrf(data);
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash((data && (data.message || data.error)) ? (data.message || data.error) : 'Upload failed.', true);
          return;
        }
        if (data.result && data.result.theme_id) {
          refillThemes(data.digit_themes || [], String(data.result.theme_id));
        }
        if (uploadPanel) uploadPanel.hidden = true;
        if (nameField) nameField.value = '';
        if (fileField) fileField.value = '';
        setFlash('Digit theme uploaded.', false);
      };
      xhr.send(fd);
    });
  }

  if (deleteBtn && form) {
    deleteBtn.addEventListener('click', function(){
      if (deleteBtn.disabled) return;
      if (!confirm('Delete the selected uploaded theme?')) return;
      var fd = 'csrf=' + encodeURIComponent(form.querySelector('[name="csrf"]').value || '') + '&theme_id=' + encodeURIComponent(themeSelect ? themeSelect.value : '');
      setFlash('Deleting theme...', false);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', form.getAttribute('data-delete-url') || '', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        var data = null;
        try { data = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        updateCsrf(data);
        if (xhr.status !== 200 || !data || data.ok !== true) {
          setFlash((data && (data.message || data.error)) ? (data.message || data.error) : 'Delete failed.', true);
          return;
        }
        refillThemes(data.digit_themes || [], 'default');
        setFlash('Theme deleted.', false);
      };
      xhr.send(fd);
    });
  }

  [form, counterSnippet, mapSnippet, resetBtn, displayModeSelect, themeSelect, digitHeightInput, showUploadBtn, hideUploadBtn, uploadBtn, deleteBtn].forEach(stopDragPropagation);
  if (displayModeSelect) displayModeSelect.addEventListener('change', refreshThemeUi);
  if (themeSelect) themeSelect.addEventListener('change', refreshThemeUi);
  if (digitHeightInput) digitHeightInput.addEventListener('input', renderPreview);
  refreshThemeUi();
})();
</script>
