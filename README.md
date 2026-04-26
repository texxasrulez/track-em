# Track 'Em - Privacy‑friendly, self‑hosted site analytics (PHP)

![Downloads](https://img.shields.io/github/downloads/texxasrulez/track-em/total?style=plastic&logo=github&logoColor=white&label=Downloads&labelColor=aqua&color=blue)
![GitHub Downloads (all assets, all releases)](https://img.shields.io/github/downloads/texxasrulez/track-em/total?style=plastic&logo=github&logoColor=white&label=Downloads&labelColor=blue&color=aqua)
[![Github License](https://img.shields.io/github/license/texxasrulez/track-em?style=plastic&logo=github&label=License&labelColor=blue&color=coral)](https://github.com/texxasrulez/track-em/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/texxasrulez/track-em?style=plastic&logo=github&label=Stars&labelColor=blue&color=deepskyblue)](https://github.com/texxasrulez/track-em/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/texxasrulez/track-em?style=plastic&logo=github&label=Issues&labelColor=blue&color=aqua)](https://github.com/texxasrulez/track-em/issues)
[![GitHub Contributors](https://img.shields.io/github/contributors/texxasrulez/track-em?style=plastic&logo=github&logoColor=white&label=Contributors&labelColor=blue&color=orchid)](https://github.com/texxasrulez/track-em/graphs/contributors)
[![GitHub Forks](https://img.shields.io/github/forks/texxasrulez/track-em?style=plastic&logo=github&logoColor=white&label=Forks&labelColor=blue&color=darkorange)](https://github.com/texxasrulez/track-em/forks)
[![Donate Paypal](https://img.shields.io/badge/Paypal-Money_Please!-blue.svg?style=plastic&labelColor=blue&color=forestgreen&logo=paypal)](https://www.paypal.me/texxasrulez)

Track 'Em is a no‑nonsense, PHP‑native tracker and tiny dashboard you can drop into any site or subfolder. It records page views and lightweight metadata, respects user privacy, and gives you simple, fast admin views - **no external SaaS, no cookies unless you enable consent**, and no build toolchain.

---

## **Screenshot**

![Alt text](/assets/images/screenshot.png?raw=true "Track Em Screenshot")

---

## Highlights

- **Zero‑dependency PHP app** - no Composer, no Node. Just PHP 8.0+ and MySQL/MariaDB.
- **Privacy first** - honors Do‑Not‑Track; optional consent banner; IP anonymization/masking.
- **Geo** - pluggable providers: free `ip-api.com` (with proxy option) or local **MaxMind GeoLite2 City** `.mmdb`.
- **Fast** - single PDO connection; prepared statements; no ORM.
- **Admin UI** - visitors table, charts/widgets, themes, a two-pane plugin manager, and full settings.
- **Plugins** - route-driven plugin system with first-party widgets, embeds, event collection, goals, referrer analysis, alerts, bot detection, and static reporting.
- **I18n** - simple label files under `i18n/` with a validator, plus per‑session language switching.
- **Operational sanity** - retention script, rate limiting on API, cron for Geo DB refresh.

---

## Documentation

- [docs/README.md](docs/README.md) - docs index and root-webroot notes.
- [docs/PLUGIN_DEVELOPMENT.md](docs/PLUGIN_DEVELOPMENT.md) - detailed third-party plugin development guide.
- [docs/I18N.md](docs/I18N.md) - localization notes.
- [docs/TRANSLATION.md](docs/TRANSLATION.md) - translation workflow notes.

---

## Folder layout

```
track-em/
├─ index.php                 # Front controller → Bootstrap → Router
├─ track.php                 # Beacon/pixel endpoint (POST JSON or 1×1 GIF fallback)
├─ install.php               # Guided installer + DB bootstrap + admin setup
├─ sql/schema.sql            # Database schema (users, visits, geo_cache)
├─ assets/                   # App JS/CSS (no bundler)
│  └─ js/te.js               # Copy‑paste client snippet (sets TE_ENDPOINT → /track.php)
├─ app/
│  ├─ core/                  # Framework‑ish bits (Config, DB, Router, Security, Geo, Theme, I18n, HookManager, PluginDispatcher)
│  ├─ controllers/           # Admin/API endpoints
│  ├─ models/                # Visit, User
│  └─ views/                 # Admin HTML templates + layout
├─ app/plugins/              # Built‑in plugins (widgets, public embeds, goals, alerts, reports, etc.)
├─ docs/                     # Setup, translation, and plugin development docs
├─ themes/                   # Theme placeholders (runtime CSS is generated)
├─ config/
│  ├─ config.sample.php      # Copy to config.php and edit
│  └─ .installed.lock        # Created by installer
├─ data/GeoLite2-City.mmdb   # Optional: local MaxMind DB (auto‑download helper provided)
├─ cli/                      # Operational scripts (cron‑safe)
│  ├─ geo_cron.php           # Nightly GeoLite2 refresh + backfill recent visits
│  └─ validate_locales.php   # Ensures i18n placeholders/keys are consistent
└─ scripts/retention_purge.php # Purge old visits according to config.retention.days
```

---

## Requirements

- PHP 8.0+ with PDO MySQL, JSON, cURL, Phar (for GeoLite2 tar extraction).
- MySQL or MariaDB.
- Web server (Apache, Nginx, Caddy) that can serve the folder or a sub‑path.
- Optional: **MaxMind GeoLite2** license key if you want local geo. A free key is available from MaxMind.

---

## Quick start (5 minutes)

1. **Copy files** to your server (e.g., `/var/www/track-em`). The app happily lives at a sub‑path like `/track-em`.
2. **Visit** `https://yourdomain/track-em/install.php`.
3. Fill in:
   - Base URL (e.g., `/track-em` if in a subfolder).
   - Database credentials (the installer creates/repairs tables; it never drops data).
   - Admin username/password.
   - Optional geo provider config.
4. Submit the form. On success you’ll be redirected to the **Login** page. The installer writes:
   - `config/config.php`
   - `config/.installed.lock`
5. **Log in** and explore the Admin dashboard.

> Re‑running the installer is safe: it upgrades tables and seeds defaults; it won’t drop existing data.

---

## Tracking: add the snippet to your site

### Simple JS beacon (preferred)

```html
<script>
  // If Track 'Em is not at site root, set TE_ENDPOINT explicitly
  window.TE_ENDPOINT = "/track-em/track.php";
</script>
<script async src="/track-em/assets/js/te.js"></script>
```

This sends a small JSON payload (`path`, `lang`, `screen` info). If `fetch()` fails, it falls back to a 1×1 GIF ping.

### Pixel‑only fallback (no JS)

```html
<img
  src="/track-em/track.php?p=/the/page&ts=<?=time()?>"
  alt=""
  width="1"
  height="1"
  style="position:absolute;left:-9999px;"
/>
```

Append `?debug=1` to `track.php` while testing to get JSON back instead of a GIF.

---

## Configuration

Copy `config/config.sample.php` → `config/config.php` and adjust. Full sample for reference:

```php
<?php
return [
  "base_url" => "",
  "database" => [
    "host" => "127.0.0.1",
    "port" => 3306,
    "name" => "",
    "user" => "",
    "pass" => "",
  ],
  "theme" => [
    "active" => "default",
  ],
  "i18n" => [
    "default" => "en_US",
  ],
  "privacy" => [
    "respect_dnt" => true,
    "require_consent" => false,
    "ip_anonymize" => true,
    "ip_mask_bits" => 16,
  ],
  "security" => [
    "trusted_proxies" => [],
  ],
  "geo" => [
    "enabled" => true,
    "provider" => "ip-api",
    "ip_api_base" => "http://ip-api.com/json", // or point to your own HTTPS proxy
    "allow_insecure_http" => false,
  ],
];
```

Key options (practical notes):

- `base_url`: The mount path (e.g., `''` when at web root, or `/track-em` in a subfolder). Affects asset links and redirects.
- `database`: MySQL/MariaDB connection - defaults live in code, but set these explicitly in production.
- `theme.active`: `"default"` or `"dark"` (runtime CSS is generated; placeholders exist under `/themes`).
- `i18n.default`: Default locale like `en_US`. Upload `i18n/{LOCALE}.php` files to add languages.
- `privacy`:
  - `respect_dnt` - Skip tracking when the browser's Do‑Not‑Track is on.
  - `require_consent` - Show/use the consent banner plugin and only track after "Allow".
  - `ip_anonymize` + `ip_mask_bits` - Masks the lower bits of IPs before storing.
- `security`:
  - `trusted_proxies` - Optional list of reverse proxy IPs allowed to supply `X-Forwarded-For`. Leave empty when the app is directly exposed.
- `geo`:
  - `enabled` - store lat/lon + city/country when available.
  - `provider` - `"ip-api"` or MaxMind providers.
  - `ip_api_base` - Point to an HTTPS endpoint or your own HTTPS proxy.
  - `allow_insecure_http` - Defaults to `false`. Set `true` only if you explicitly accept plaintext geo lookups.
  - `mm_license_key`, `mmdb_path` - For MaxMind local DB (auto‑download supported).
- `rate_limit`:
  - `enabled` - Throttle API endpoints.
  - `window_sec`, `max_events` - e.g., 60 seconds, 120 events.
- `retention.days` - Used by `scripts/retention_purge.php` to delete old rows.

---

## Database

Schema lives in `sql/schema.sql` and includes three tables:

- `users(id, username, password_hash, role, created_at)`
- `visits(id, ip, user_agent, referrer, path, ts, meta, lat, lon, city, country)`
- `geo_cache(ip, lat, lon, city, country, updated_at)`

Migrations are handled pragmatically at runtime (`Visit::ensureColumns()`) and by the installer. No separate migration runner.

---

## Admin UI & routes

Public entry points are parameterized via `?p=`. Core routes:

- `admin`
- `admin.help`
- `admin.plugins`
- `admin.settings`
- `admin.themes`
- `admin.users`
- `admin.visitors`
- `api.geo`
- `api.geo.test`
- `api.health`
- `api.layout.get`
- `api.layout.save`
- `api.plugins`
- `api.plugins.asset`
- `api.plugins.config.set`
- `api.plugins.configs`
- `api.plugins.install`
- `api.plugins.list`
- `api.plugins.remove`
- `api.plugins.toggle`
- `api.realtime`
- `api.stream`
- `login`
- `logout`

Key screens:

- **Dashboard**: simple metrics and widgets.
- **Visitors**: filterable table, recent activity.
- **Themes**: pick theme; runtime CSS vars sanitized.
- **Plugins**: enable/disable and configure plugins (see below).
- **Settings**: privacy, geo, rate limits, retention, base URL.
- **Users**: manage admin accounts.
- **Help**: quick reference.

Plugin-owned routes are also supported. Any route of the form `plugin_id.action` is passed to `TrackEm\Core\PluginDispatcher`, which loads `app/plugins/{plugin_id}/PluginController.php` and calls its `dispatch()` method.

---

## Plugins

Built-in plugins ship under `app/plugins/` and are managed via the **Plugins** screen and API.

Current first-party plugins in this repository include:

- `consent_banner`
- `visitors`
- `realtime`
- `maps`
- `public_widgets`
- `event_tracking`
- `goals`
- `referrer_intel`
- `traffic_alerts`
- `bot_watch`
- `static_reports`

The current plugin manager supports:

- a real plugin sidebar with filtering and search
- plugin-specific admin routes via `plugin.json -> admin_route`
- generic schema-based settings forms via `plugin.json -> configSchema`

Common asset and config patterns:

- static plugin asset route: `?p=api.plugins.asset&key={id}&file=assets/widget.js`
- plugin-owned route example: `?p=event_tracking.asset&file=trackem-events.js`
- plugin runtime storage: `storage/plugins/{id}/...`
- older generic config storage: `config/plugins/{id}.json`

For real third-party development, use [docs/PLUGIN_DEVELOPMENT.md](docs/PLUGIN_DEVELOPMENT.md). It documents the current `plugin.json` + `PluginController.php` conventions and the plugin route dispatcher used by this codebase.

---

## Geo options

- **ip-api.com**: simple web API. By default Track 'Em refuses plaintext HTTP lookups, so use an HTTPS proxy or explicitly opt into insecure HTTP with `geo.allow_insecure_http=true`.
- **MaxMind GeoLite2 (local)**: set `geo.mm_license_key` in **Settings**, then click **Download / Update GeoLite2**. The app uses `PharData` to unpack the official tarball and writes `data/GeoLite2-City.mmdb`. You can also run the cron below.

Nightly cron to refresh the DB and backfill recent visits:

```cron
17 2 * * * php /path/to/track-em/cli/geo_cron.php >> /path/to/track-em/storage/geo_cron.log 2>&1
```

---

## Retention & housekeeping

Prune old visits (based on `retention.days`) via cron:

```cron
# daily at 03:11
11 3 * * * php /path/to/track-em/scripts/retention_purge.php
```

The script uses the configured PDO connection and deletes rows older than `NOW() - INTERVAL {days} DAY`.

---

## I18n

- Locale files live in `i18n/{LOCALE}.php` and return `['key' => 'Label', ...]`.
- Default locale is `i18n.default`. Users can switch with `?lang=xx_YY`.
- Create or update locale files with DeepL using the append-only helper in `scripts/translate_locales.php`.
- Validate your locales (keys and placeholders) locally:

```bash
php cli/validate_locales.php
```

- Generate or update missing translations with DeepL:

```bash
export DEEPL_API_KEY=your-key
php scripts/translate_locales.php --source=en_US
php scripts/translate_locales.php --only=fr_FR,es_ES
php scripts/translate_locales.php --dry-run
php scripts/translate_locales.php --force
```

- DeepL endpoint selection:
  - `DEEPL_API_KEY` is required unless `--dry-run` is used.
  - `DEEPL_API_URL` is optional; if unset, the script auto-selects `https://api-free.deepl.com` for DeepL Free keys and `https://api.deepl.com` otherwise.
  - `MT_FORMALITY` can be set to `default`, `more`, or `less`.
- Normal runs are append-only:
  - The script sends only missing keys from `i18n/en_US.php` to DeepL.
  - Existing translated strings are not modified.
  - Use `--force` only when you intentionally want to re-translate existing keys.
- Outputs:
  - Updated `i18n/<locale>.php` files when missing keys are filled.
  - `i18n/.mt/mt_report.json` with a summary of what was processed.
  - `i18n/.mt/mt_state.json` with source metadata.

---

## Security notes

- Sessions use `SameSite=Lax`, `HttpOnly`, and detect HTTPS behind proxies; installer uses CSRF tokens.
- Optional IP anonymization masks addresses before persistence.
- API endpoints are rate‑limited when enabled.
- Admin actions write configs atomically and invalidate OPcache when present.

---

## Running behind a sub‑path

Set `base_url` appropriately (e.g., `/track-em`). Example Nginx snippet:

```nginx
location /track-em/ {
  alias  /var/www/track-em/;
  index  index.php;
  try_files $uri $uri/ /track-em/index.php?$args;

  location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $request_filename;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock; # adjust
  }
}
```

Apache usually works out of the box with `index.php?p=...` routing; you can add `FallbackResource index.php` if needed.

---

## Backups & upgrades

- Back up `config/`, `data/GeoLite2-City.mmdb` (if used), and your database.
- Upgrades are file‑level: replace files, then visit `/install.php` to apply any schema updates.

---

## Troubleshooting

- **White screen / 500**: check web server error logs. The app writes minimal errors via `error_log()`.
- **DB connection failed**: verify MySQL credentials in `config/config.php`.
- **Admin redirect loop**: ensure `config/.installed.lock` exists and `base_url` is correct.
- **Geo lookup returns null**: private IPs are ignored; confirm provider settings.
- **Assets not loading under sub‑path**: set `base_url` and ensure your web server `try_files` points to `index.php`.
- **Locales**: run `php cli/validate_locales.php`.

---

## Donations

This project uses a library written by a Ukranian citizen who is asking for help for his fellow Ukranians from Russian aggression. Lets help out Ukraine.

https://leafletjs.com/

---
