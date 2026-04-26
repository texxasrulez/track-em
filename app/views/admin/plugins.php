<?php
use TrackEm\Core\I18n;
use TrackEm\Core\Security;

function h($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES);
}

function plugins_url(array $overrides = []): string
{
    global $pluginQuery, $filterQuery, $searchQuery;
    $params = ["p" => "admin.plugins"];
    if ($pluginQuery !== "") {
        $params["plugin"] = $pluginQuery;
    }
    if ($filterQuery !== "all") {
        $params["filter"] = $filterQuery;
    }
    if ($searchQuery !== "") {
        $params["q"] = $searchQuery;
    }
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === "") {
            unset($params[$key]);
            continue;
        }
        $params[$key] = $value;
    }
    return "?" . http_build_query($params);
}

function plugin_display_name(array $plugin): string
{
    return (string) (($plugin["meta"]["name"] ?? "") ?: ($plugin["key"] ?? ""));
}

function plugin_description(array $plugin): string
{
    return (string) ($plugin["meta"]["description"] ?? "");
}

function selected_plugin_value(array $plugin, array $field)
{
    $name = (string) ($field["name"] ?? "");
    if ($name === "") {
        return null;
    }
    if (array_key_exists($name, $plugin["config"] ?? [])) {
        return $plugin["config"][$name];
    }
    return $field["default"] ?? null;
}

$selectedKey = (string) ($selectedPlugin["key"] ?? "");
$selectedMeta = is_array($selectedPlugin["meta"] ?? null)
    ? $selectedPlugin["meta"]
    : [];
$selectedSchema = is_array($selectedPlugin["schema"] ?? null)
    ? $selectedPlugin["schema"]
    : ["fields" => []];
$selectedHasAdminRoute = !empty($selectedMeta["admin_route"]);
$selectedHasSchema = !empty($selectedSchema["fields"]);
$csrf = Security::csrfToken();
?>
<style>
  .plg-manager {
    display: grid;
    grid-template-columns: 320px minmax(0, 1fr);
    gap: 16px;
    align-items: start;
  }
  .plg-sidebar,
  .plg-detail {
    border: 1px solid var(--border);
    border-radius: 14px;
    background: color-mix(in srgb, var(--panel, var(--card, #fff)) 94%, transparent);
  }
  .plg-sidebar {
    position: sticky;
    top: 12px;
    overflow: hidden;
    max-height: calc(100vh - 120px);
    display: flex;
    flex-direction: column;
  }
  .plg-sidebar-head,
  .plg-detail-head,
  .plg-sidebar-foot {
    padding: 14px;
  }
  .plg-sidebar-head {
    border-bottom: 1px solid var(--border);
  }
  .plg-sidebar-foot {
    border-top: 1px solid var(--border);
  }
  .plg-search {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    margin-top: 10px;
  }
  .plg-search input[type="search"] {
    width: 100%;
    box-sizing: border-box;
    background: var(--muted);
    color: var(--text);
    border: 1px solid var(--border);
  }
  .plg-list {
    overflow: auto;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .plg-link {
    display: block;
    text-decoration: none;
    color: inherit;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    background: rgba(255,255,255,0.04);
  }
  .plg-link:hover,
  .plg-link:focus {
    background: var(--muted);
    outline: none;
  }
  .plg-link.is-active {
    border-color: var(--te-primary-border, var(--border));
    background: color-mix(in srgb, var(--te-primary-bg, #2563eb) 10%, transparent);
    box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--te-primary-border, #2563eb) 40%, transparent);
  }
  .plg-link-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }
  .plg-link-name {
    font-weight: 600;
    line-height: 1.2;
  }
  .plg-link-desc {
    margin-top: 4px;
    font-size: 12px;
    color: var(--text-soft, var(--text));
    opacity: 0.8;
  }
  .plg-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 11px;
    white-space: nowrap;
  }
  .plg-status-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: #64748b;
    flex: 0 0 auto;
  }
  .plg-status.is-enabled .plg-status-dot {
    background: #16a34a;
  }
  .plg-status.is-disabled .plg-status-dot {
    background: #b91c1c;
  }
  .plg-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }
  .plg-filter {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 5px 10px;
    border: 1px solid var(--border);
    border-radius: 999px;
    text-decoration: none;
    color: inherit;
    font-size: 12px;
    background: rgba(255,255,255,0.04);
  }
  .plg-filter.is-active {
    background: var(--te-primary-bg);
    color: var(--te-primary-text);
    border-color: var(--te-primary-border);
  }
  .plg-detail-head {
    border-bottom: 1px solid var(--border);
  }
  .plg-detail-body {
    padding: 14px;
  }
  .plg-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 8px;
    font-size: 13px;
  }
  .plg-version,
  .plg-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 999px;
    border: 1px solid var(--border);
    background: rgba(255,255,255,0.04);
  }
  .plg-summary {
    margin-top: 10px;
    max-width: 70ch;
  }
  .plg-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 14px;
    align-items: center;
  }
  .plg-inline-form {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    flex: 0 0 auto;
  }
  .plg-actions .button,
  .plg-actions button,
  .plg-inline-form .button,
  .plg-inline-form button {
    width: auto;
    flex: 0 0 auto;
  }
  .plg-note,
  .plg-empty {
    font-size: 13px;
  }
  .plg-empty {
    padding: 24px 14px;
    text-align: center;
  }
  .plg-generic {
    margin-top: 16px;
  }
  .plg-generic-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 12px;
  }
  .plg-generic-grid label {
    display: block;
    font-size: 13px;
  }
  .plg-generic input[type="text"],
  .plg-generic input[type="number"],
  .plg-generic select {
    width: 100%;
    box-sizing: border-box;
    background: var(--muted);
    color: var(--text);
    border: 1px solid var(--border);
  }
  .plg-field-help {
    margin-top: 4px;
    font-size: 12px;
    opacity: 0.8;
  }
  .plg-invalid {
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(185, 28, 28, 0.12);
    border: 1px solid rgba(185, 28, 28, 0.25);
    color: inherit;
  }
  @media (max-width: 960px) {
    .plg-manager {
      grid-template-columns: 1fr;
    }
    .plg-sidebar {
      position: static;
      max-height: none;
    }
  }
</style>

<div class="plg-manager">
  <aside class="plg-sidebar" aria-label="Plugins">
    <div class="plg-sidebar-head">
      <h3 style="margin:0"><?= I18n::t("plugins", "Plugins") ?></h3>
      <p class="note" style="margin:6px 0 0"><?= I18n::t(
          "plugins_note",
          "Manage plugins. Select a plugin to view its details and settings.",
      ) ?></p>

      <form method="get" action="" class="plg-search">
        <input type="hidden" name="p" value="admin.plugins">
        <input type="hidden" name="filter" value="<?= h($filterQuery) ?>">
        <?php if ($selectedKey !== ""): ?>
          <input type="hidden" name="plugin" value="<?= h($selectedKey) ?>">
        <?php endif; ?>
        <input
          type="search"
          name="q"
          value="<?= h($searchQuery) ?>"
          placeholder="Search plugins"
          aria-label="Search plugins"
          data-plugin-search>
        <button type="submit" class="button btn disable">Search</button>
      </form>
    </div>

    <div class="plg-list" id="plugin-sidebar-list">
      <?php if ($filteredPlugins): ?>
        <?php foreach ($filteredPlugins as $plugin): ?>
          <?php
          $key = (string) ($plugin["key"] ?? "");
          $isActive = $selectedKey !== "" && $selectedKey === $key;
          $statusClass = !empty($plugin["enabled"]) ? "is-enabled" : "is-disabled";
          ?>
          <a
            class="plg-link <?= $isActive ? "is-active" : "" ?>"
            href="<?= h(plugins_url(["plugin" => $key])) ?>"
            data-plugin-link
            data-plugin-search-text="<?= h(
                strtolower(
                    implode(
                        "\n",
                        [
                            $key,
                            plugin_display_name($plugin),
                            plugin_description($plugin),
                        ],
                    ),
                ),
            ) ?>">
            <span class="plg-link-top">
              <span class="plg-link-name"><?= h(plugin_display_name($plugin)) ?></span>
              <span class="plg-status <?= $statusClass ?>">
                <span class="plg-status-dot" aria-hidden="true"></span>
                <?= !empty($plugin["enabled"]) ? "Enabled" : "Disabled" ?>
              </span>
            </span>
            <?php if (plugin_description($plugin) !== ""): ?>
              <span class="plg-link-desc"><?= h(plugin_description($plugin)) ?></span>
            <?php endif; ?>
          </a>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="plg-empty">
          <?= $plugins
              ? "No plugins match the current filter."
              : "No plugins installed yet." ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="plg-sidebar-foot">
      <div class="plg-filters" aria-label="Plugin filters">
        <?php foreach ([
            "all" => "All",
            "enabled" => "Enabled",
            "disabled" => "Disabled",
            "settings" => "Has Settings",
        ] as $filterId => $label): ?>
          <a
            class="plg-filter <?= $filterQuery === $filterId ? "is-active" : "" ?>"
            href="<?= h(
                plugins_url([
                    "filter" => $filterId === "all" ? null : $filterId,
                ]),
            ) ?>">
            <?= h($label) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </aside>

  <section class="plg-detail">
    <div class="plg-detail-head">
      <form
        id="plg-upload"
        class="plg-inline-form"
        enctype="multipart/form-data"
        method="post"
        action="?p=api.plugins.install">
        <input type="file" name="plugin_zip" accept=".zip" required class="ms-popup">
        <button type="submit" class="button btn disable"><?= I18n::t(
            "upload_install",
            "Upload &amp; Install",
        ) ?></button>
      </form>
    </div>

    <div class="plg-detail-body">
      <?php if ($selectedPluginMessage !== ""): ?>
        <div class="plg-invalid"><?= h($selectedPluginMessage) ?></div>
      <?php endif; ?>

      <?php if ($selectedPlugin === null): ?>
        <div class="plg-empty">Select a plugin from the sidebar to view its details.</div>
      <?php else: ?>
        <h3 style="margin:0"><?= h(plugin_display_name($selectedPlugin)) ?></h3>
        <div class="plg-meta">
          <span class="plg-chip">
            <?= !empty($selectedPlugin["enabled"]) ? "Enabled" : "Disabled" ?>
          </span>
          <?php if (!empty($selectedMeta["version"])): ?>
            <span class="plg-version">v<?= h($selectedMeta["version"]) ?></span>
          <?php endif; ?>
          <span class="plg-chip"><?= h($selectedKey) ?></span>
          <?php if (!empty($selectedPlugin["has_settings"])): ?>
            <span class="plg-chip">Has settings</span>
          <?php endif; ?>
        </div>

        <?php if (plugin_description($selectedPlugin) !== ""): ?>
          <p class="plg-summary"><?= h(plugin_description($selectedPlugin)) ?></p>
        <?php endif; ?>

        <div class="plg-actions">
          <form method="post" action="<?= h(plugins_url()) ?>" class="plg-inline-form">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="plugin" value="<?= h($selectedKey) ?>">
            <input type="hidden" name="filter" value="<?= h($filterQuery) ?>">
            <input type="hidden" name="q" value="<?= h($searchQuery) ?>">
            <input type="hidden" name="plugin_id" value="<?= h($selectedKey) ?>">
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="enabled" value="<?= !empty($selectedPlugin["enabled"])
                ? "0"
                : "1" ?>">
            <button type="submit" class="button btn disable">
              <?= !empty($selectedPlugin["enabled"]) ? "Disable" : "Enable" ?>
            </button>
          </form>

          <button
            type="button"
            class="button danger"
            data-plugin-remove
            data-plugin-key="<?= h($selectedKey) ?>">
            Remove
          </button>
        </div>

        <?php if ($selectedHasAdminRoute && $selectedPluginHtml !== ""): ?>
          <div style="margin-top:16px"><?= $selectedPluginHtml ?></div>
        <?php elseif ($selectedHasSchema): ?>
          <form method="post" action="<?= h(plugins_url()) ?>" class="plg-generic">
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="plugin" value="<?= h($selectedKey) ?>">
            <input type="hidden" name="filter" value="<?= h($filterQuery) ?>">
            <input type="hidden" name="q" value="<?= h($searchQuery) ?>">
            <input type="hidden" name="plugin_id" value="<?= h($selectedKey) ?>">
            <input type="hidden" name="action" value="save">

            <div class="plg-generic-grid">
              <?php foreach ($selectedSchema["fields"] as $field): ?>
                <?php
                $fieldName = (string) $field["name"];
                $value = selected_plugin_value($selectedPlugin, $field);
                $type = (string) ($field["type"] ?? "text");
                ?>
                <label>
                  <?= h((string) ($field["label"] ?? $fieldName)) ?>
                  <?php if ($type === "select"): ?>
                    <select name="cfg[<?= h($selectedKey) ?>][<?= h($fieldName) ?>]">
                      <?php foreach (($field["options"] ?? []) as $optionValue => $optionLabel): ?>
                        <option value="<?= h($optionValue) ?>" <?= (string) $value === (string) $optionValue
                            ? "selected"
                            : "" ?>><?= h($optionLabel) ?></option>
                      <?php endforeach; ?>
                    </select>
                  <?php elseif ($type === "checkbox"): ?>
                    <div style="margin-top:8px">
                      <input
                        type="checkbox"
                        name="cfg[<?= h($selectedKey) ?>][<?= h($fieldName) ?>]"
                        value="on"
                        <?= !empty($value) ? "checked" : "" ?>>
                    </div>
                  <?php else: ?>
                    <input
                      type="<?= $type === "number" ? "number" : "text" ?>"
                      name="cfg[<?= h($selectedKey) ?>][<?= h($fieldName) ?>]"
                      value="<?= h((string) $value) ?>">
                  <?php endif; ?>
                  <?php if (!empty($field["help"])): ?>
                    <div class="plg-field-help"><?= h((string) $field["help"]) ?></div>
                  <?php endif; ?>
                </label>
              <?php endforeach; ?>
            </div>

            <div class="plg-actions">
              <button type="submit" class="button btn disable">Save Settings</button>
            </div>
          </form>
        <?php else: ?>
          <div class="plg-note" style="margin-top:16px">
            This plugin does not expose configurable settings.
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
</div>

<script>
(function(){
  var searchInput = document.querySelector('[data-plugin-search]');
  var list = document.getElementById('plugin-sidebar-list');
  var removeBtn = document.querySelector('[data-plugin-remove]');
  var csrf = <?= json_encode($csrf) ?>;

  if (searchInput && list) {
    searchInput.addEventListener('input', function(){
      var q = String(searchInput.value || '').toLowerCase();
      var links = list.querySelectorAll('[data-plugin-link]');
      for (var i = 0; i < links.length; i++) {
        var link = links[i];
        var text = String(link.getAttribute('data-plugin-search-text') || '');
        link.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
      }
    });
  }

  if (removeBtn) {
    removeBtn.addEventListener('click', function(){
      var key = removeBtn.getAttribute('data-plugin-key') || '';
      if (!key || !confirm('Remove plugin ' + key + '?')) return;
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '?p=api.plugins.remove&key=' + encodeURIComponent(key), true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;
        window.location.href = <?= json_encode(plugins_url(["plugin" => null])) ?>;
      };
      xhr.send('csrf=' + encodeURIComponent(csrf));
    });
  }
})();
</script>
