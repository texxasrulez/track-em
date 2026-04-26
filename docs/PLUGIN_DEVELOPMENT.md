# Track Em Plugin Development Guide

This document describes how third-party plugins work in the current Track Em codebase.

It is intentionally detailed and is based on the actual conventions in this repository.

## Overview

Track Em plugins are folder-based packages under `app/plugins/{plugin_id}`.

The current plugin system supports three practical integration styles:

1. Metadata-only plugins with a simple `configSchema`
2. Full admin plugins with a `PluginController.php` and a plugin-owned `admin_route`
3. Hybrid plugins that expose both a schema and richer custom routes/UI

The loading model is intentionally simple:

- plugin discovery is filesystem-based via `app/plugins/*/plugin.json`
- plugin enabled state is stored in the `plugins` database table
- plugin admin pages are rendered inside the main plugin manager
- plugin-owned routes are dispatched through `TrackEm\Core\PluginDispatcher`

## Core Files To Know

- `app/core/PluginRegistry.php`
- `app/core/PluginDispatcher.php`
- `app/core/Router.php`
- `app/controllers/AdminController.php`
- `app/controllers/_addons/ApiPluginsAddon.php`
- `app/views/admin/plugins.php`

## Minimum Plugin Layout

Typical full plugin layout:

```text
app/plugins/your_plugin/
├─ plugin.json
├─ config.json
├─ PluginController.php
├─ YourPluginService.php
├─ README.md
├─ views/
│  └─ admin_fragment.php
└─ assets/
   ├─ widget.js
   ├─ your-plugin.js
   └─ your-plugin.css
```

Minimal schema-only plugin layout:

```text
app/plugins/your_plugin/
├─ plugin.json
└─ config.json
```

## `plugin.json`

`plugin.json` is the manifest used by discovery and the plugin manager.

Typical fields:

```json
{
  "id": "your_plugin",
  "name": "Your Plugin",
  "version": "0.1.0",
  "description": "Short admin-facing description.",
  "admin_route": "your_plugin.admin",
  "configSchema": {
    "fields": [
      {
        "name": "enabled",
        "label": "Enable plugin",
        "type": "checkbox",
        "default": true,
        "help": "Turns the plugin on or off."
      }
    ]
  }
}
```

Common fields in this repo:

- `id`
- `name`
- `version`
- `description`
- `admin_route`
- `configSchema`

Rules:

- `id` should match the plugin folder name
- use lowercase letters, numbers, and underscores
- `admin_route` should use the form `{plugin_id}.admin`
- `configSchema` is optional and only needed for the generic schema fallback

## Route Dispatch Model

Plugin routes look like this:

- `?p=your_plugin.admin`
- `?p=your_plugin.save`
- `?p=your_plugin.reset`
- `?p=your_plugin.asset&file=widget.js`

`app/core/PluginDispatcher.php` validates routes and then loads:

- `app/plugins/{plugin_id}/PluginController.php`
- `TrackEm\Plugins\{PluginIdPascalCase}\PluginController`

It then calls:

```php
public function dispatch(string $action): bool
```

Class naming convention:

- plugin id: `public_widgets`
- namespace/class: `TrackEm\Plugins\PublicWidgets\PluginController`

## Controller Convention

Most first-party plugins use a controller like this:

```php
<?php
declare(strict_types=1);

namespace TrackEm\Plugins\YourPlugin;

use TrackEm\Core\Security;

require_once dirname(__DIR__, 2) . '/core/Security.php';
require_once dirname(__DIR__, 2) . '/core/DB.php';
require_once __DIR__ . '/YourPluginService.php';

final class PluginController
{
    private YourPluginService $service;
    private string $pluginDir;

    public function __construct(string $pluginId, string $pluginDir)
    {
        $this->pluginDir = rtrim($pluginDir, '/\\');
        $this->service = new YourPluginService($pluginId, $pluginDir);
    }

    public function dispatch(string $action): bool
    {
        return match ($action) {
            'admin' => $this->admin(),
            'save' => $this->save(),
            'reset' => $this->reset(),
            default => false,
        };
    }
}
```

Patterns worth following:

- keep the controller thin
- put storage, reporting, and sanitization in a service class
- centralize permission checks in `requireAdmin()` and `requireAdminPost()`
- return JSON for save/reset/rebuild endpoints
- set explicit content types and cache headers

## Admin UI Integration

The plugin manager in `app/views/admin/plugins.php` renders a selected plugin in the right detail pane.

It supports two admin rendering modes.

### Mode 1: Rich custom fragment

If `plugin.json` contains `admin_route`, `AdminController::renderPluginAdminPanel()` calls:

```php
\TrackEm\Core\PluginDispatcher::dispatch($route)
```

Your controller's `admin()` action can then:

- load config
- compute reports
- generate CSRF token
- `require` a plugin-local view such as `views/admin_fragment.php`

This is the preferred pattern for non-trivial plugins.

### Mode 2: Schema-only fallback

If `plugin.json` contains `configSchema`, the plugin manager can render a generic settings form even without a custom controller.

Supported schema field types in the current code:

- `text`
- `number`
- `checkbox`
- `select`

Use this only for very small plugins.

## Storage Conventions

There are two storage styles in the repo today.

### Preferred newer pattern

Use plugin-owned runtime storage under:

```text
storage/plugins/{plugin_id}/
```

Examples from first-party plugins:

- `storage/plugins/public_widgets/config.json`
- `storage/plugins/goals/rollups.json`
- `storage/plugins/event_tracking/events-YYYY-MM.jsonl`
- `storage/plugins/traffic_alerts/alerts.jsonl`
- `storage/plugins/static_reports/reports/*.html`

This is the preferred pattern for new plugins.

### Older compatibility pattern

Some generic plugin APIs also read and write:

```text
config/plugins/{plugin_id}.json
```

That path still matters for:

- generic plugin config APIs
- some older plugins
- schema-only plugins

Recommendation:

- If your plugin owns a real controller and service, prefer `storage/plugins/{plugin_id}/...`.
- If your plugin is schema-only and relies on the generic APIs, `config/plugins/{plugin_id}.json` is acceptable.
- Do not scatter runtime files across both locations unless you have a clear compatibility reason.

## Plugin Assets

There are two asset models in Track Em.

### Generic static asset route

Served by:

```text
?p=api.plugins.asset&key={plugin_id}&file=assets/widget.js
```

This is useful for:

- dashboard widget assets
- simple static JS/CSS/image files

### Plugin-owned asset route

Many newer plugins expose their own route, for example:

```text
?p=event_tracking.asset&file=trackem-events.js
?p=public_widgets.asset&file=counter.js
```

Use a plugin-owned route when you need:

- custom validation
- custom cache policy
- custom content type logic
- a safer public contract

## Dashboard Widgets

The admin dashboard can load plugin widgets from `assets/widget.js`.

Practical notes:

- widget scripts should fail silently if the dashboard context is not present
- widget scripts should not assume the plugin is selected in the plugin manager
- widget scripts should keep network usage light

## Security Expectations

Third-party plugins should follow the same defensive style used by first-party plugins.

### Admin access

Admin-only routes should:

- start the secure session
- verify `$_SESSION['uid']`
- return `401` or a safe message if not authenticated

Typical pattern:

```php
private function requireAdmin(): bool
{
    Security::startSecureSession();
    if (!isset($_SESSION['uid'])) {
        http_response_code(401);
        echo 'Unauthorized';
        return false;
    }
    return true;
}
```

### CSRF

Admin POST routes should verify CSRF tokens:

```php
if (!Security::verifyCsrf((string) ($_POST['csrf'] ?? ''))) {
    $this->json(400, ['ok' => false, 'error' => 'bad_csrf']);
    return false;
}
```

### Rate limiting

If a plugin exposes write endpoints or public endpoints, use `Security::rateLimit()`.

Examples:

- admin save endpoints
- public collect endpoints
- public embed data endpoints

### Output escaping

Escape dynamic HTML output with `htmlspecialchars`.

A common pattern in plugin views is:

```php
$h = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES);
```

### Path safety

Never allow arbitrary file reads based on raw query-string input.

If your plugin serves files:

- normalize with `basename()`
- whitelist the filename pattern
- verify the resolved path stays inside the plugin folder or plugin storage folder

### Public endpoints

If your plugin has public endpoints:

- return only sanitized aggregate data unless there is a strong reason otherwise
- do not expose private visitor data
- set explicit content types
- set `X-Content-Type-Options: nosniff`
- avoid expensive unbounded scans
- fail closed when disabled

## Database Usage

Plugins can use `TrackEm\Core\DB::pdo()` directly.

Keep queries cheap:

- clamp time ranges
- prefer grouped summaries over raw scans when possible
- cache or roll up expensive reports into plugin storage
- avoid schema changes unless truly necessary

For many plugins in this repo, append-only JSONL or cached JSON files are preferred over new database tables.

## Service Class Pattern

First-party plugins usually put most logic in a service class.

Typical responsibilities:

- load defaults from `app/plugins/{id}/config.json`
- merge saved config
- sanitize config from POST input
- compute reports
- manage plugin storage files
- generate route URLs
- create snippets or embed URLs
- expose helper methods to the controller and view

This keeps the controller short and makes direct service-level verification easier.

## Config Merging

Be careful when merging arrays.

Associative maps and list arrays behave differently.

If your config contains list arrays such as:

- allowlists
- path patterns
- profile lists

then a recursive merge can accidentally merge by numeric index instead of replacing the list.

Recommended approach:

- replace list arrays outright
- only recursively merge associative maps

Several first-party plugins implement a helper similar to:

```php
private function isListArray(array $value): bool
{
    if (function_exists('array_is_list')) {
        return array_is_list($value);
    }
    return array_keys($value) === range(0, count($value) - 1);
}
```

## Optional Plugin Interoperability

If your plugin wants to integrate with another plugin:

1. Check whether its folder and files exist
2. `require_once` the other service class
3. instantiate it directly
4. degrade gracefully if it is missing

Examples in the current repo:

- `goals` integrates with `event_tracking`
- `static_reports` integrates with `referrer_intel`, `event_tracking`, `goals`, and `bot_watch`

Do not hard-fail if the optional plugin is not installed.

## Suggested Build Workflow

1. Create `app/plugins/{id}`
2. Add `plugin.json`
3. Decide whether you need:
   - `admin_route`
   - `configSchema`
   - both
4. Add `PluginController.php` if the plugin is route-driven
5. Add a service class
6. Add `views/admin_fragment.php` for richer admin UI
7. Add `assets/` if needed
8. Add a `README.md`
9. Write runtime files only under `storage/plugins/{id}` at runtime
10. verify with `php -l` and any relevant JS syntax checks

## Example Route Set

A richer plugin might expose:

- `your_plugin.admin`
- `your_plugin.save`
- `your_plugin.reset`
- `your_plugin.rebuild`
- `your_plugin.asset`
- `your_plugin.public_data`

Not every plugin needs all of these.

## Example `plugin.json`

```json
{
  "id": "my_reporter",
  "name": "My Reporter",
  "version": "1.0.0",
  "description": "Example third-party reporting plugin.",
  "admin_route": "my_reporter.admin",
  "configSchema": {
    "fields": [
      {
        "name": "enabled",
        "label": "Enable reporter",
        "type": "checkbox",
        "default": true
      },
      {
        "name": "sample_limit",
        "label": "Sample limit",
        "type": "number",
        "default": 25
      }
    ]
  }
}
```

## Save Endpoint Checklist

For a POST save route:

- require admin session
- require `POST`
- apply rate limit
- verify CSRF
- sanitize all fields
- write config atomically
- return JSON

## Public Endpoint Checklist

If you really need a public route:

- keep it read-only
- keep it aggregated
- keep it low-cost
- clamp ranges and limits
- refuse requests when disabled
- use JSON with explicit headers
- avoid raw IPs, user agents, timestamps, referrers, or metadata unless the route is private and the plugin absolutely needs them

## Report and Cache Files

If your plugin writes derived files:

- keep them under `storage/plugins/{id}/`
- use predictable names
- prune old files when appropriate
- document the format in the plugin README

Examples in the repo:

- JSONL event logs
- JSON rollups
- HTML static reports

## Testing and Verification

At minimum, verify:

- `php -l` for each new PHP file
- `node --check` for standalone plugin JS files when applicable
- config save/load round-trip
- route safety
- disabled plugin behavior
- missing optional dependency behavior
- storage cleanup or pruning behavior

If you can run against a live instance, also verify:

- plugin shows in the plugin manager
- selection persists in the URL
- admin UI renders correctly
- enable/disable still works
- reports or endpoints behave correctly with real data

## Backward Compatibility Expectations

Avoid these unless absolutely necessary:

- changing core route semantics
- changing plugin enable/disable semantics
- renaming plugin ids
- moving existing plugin config files
- adding heavy dependencies
- adding always-running workers

Prefer self-contained plugin routes and plugin-owned storage.

## First-Party Examples Worth Reading

- `app/plugins/event_tracking`
- `app/plugins/public_widgets`
- `app/plugins/goals`
- `app/plugins/referrer_intel`
- `app/plugins/traffic_alerts`
- `app/plugins/bot_watch`
- `app/plugins/static_reports`

## Final Guidance

The safest way to build a Track Em plugin today is:

- use `plugin.json`
- use `admin_route`
- build a small `PluginController`
- keep logic in a service
- store runtime files under `storage/plugins/{id}`
- escape everything you render
- keep queries bounded
- fail closed

That path matches how the current first-party plugins are built.
