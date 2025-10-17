# Track 'Em — Privacy‑friendly, self‑hosted site analytics (PHP)

[![Github License](https://img.shields.io/github/license/texxasrulez/track-em?style=plastic&logo=github&label=License&labelColor=blue&color=coral)](https://github.com/texxasrulez/track-em/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/texxasrulez/track-em?style=plastic&logo=github&label=Stars&labelColor=blue&color=deepskyblue)](https://github.com/texxasrulez/track-em/stargazers)
[![GitHub Issues](https://img.shields.io/github/issues/texxasrulez/track-em?style=plastic&logo=github&label=Issues&labelColor=blue&color=aqua)](https://github.com/texxasrulez/track-em/issues)
[![GitHub Contributors](https://img.shields.io/github/contributors/texxasrulez/track-em?style=plastic&logo=github&logoColor=white&label=Contributors&labelColor=blue&color=orchid)](https://github.com/texxasrulez/track-em/graphs/contributors)
[![GitHub Forks](https://img.shields.io/github/forks/texxasrulez/track-em?style=plastic&logo=github&logoColor=white&label=Forks&labelColor=blue&color=darkorange)](https://github.com/texxasrulez/track-em/forks)
[![Donate Paypal](https://img.shields.io/badge/Paypal-Money_Please!-blue.svg?style=plastic&labelColor=blue&color=forestgreen&logo=paypal)](https://www.paypal.me/texxasrulez)

Track 'Em is a no‑nonsense, PHP‑native tracker and tiny dashboard you can drop into any site or subfolder. 

It records page views and lightweight metadata, respects user privacy, and gives you simple, fast admin views — **no external SaaS, no cookies unless you enable consent**, and no build toolchain. 

If you are looking for something in depth like Matomo, this is not for you. This is a lightweight tracker built for the enthusiest that does not want their system resources getting hogged up. 

---

**Screenshot**
-----------

![Alt text](/assets/images/screenshot.png?raw=true "Track 'Em Screenshot")

---

## Highlights

- **Zero‑dependency PHP app** — no Composer, no Node. Just PHP 8.0+ and MySQL/MariaDB.
- **Privacy first** — honors Do‑Not‑Track; optional consent banner; IP anonymization/masking.
- **Geo** — pluggable providers: free `ip-api.com` (with proxy option) or local **MaxMind GeoLite2 City** `.mmdb`.
- **Fast** — single PDO connection; prepared statements; no ORM.
- **Admin UI** — visitors table, charts/widgets, themes, plugins, and full settings.
- **Plugins** — first‑party widgets: `visitors`, `realtime`, `maps`, `consent_banner`.
- **I18n** — simple label files under `i18n/` with a validator, plus per‑session language switching.
- **Operational sanity** — retention script, rate limiting on API, cron for Geo DB refresh.

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
│  ├─ core/                  # Framework‑ish bits (Config, DB, Router, Security, Geo, Theme, I18n, HookManager)
│  ├─ controllers/           # Admin/API endpoints
│  ├─ models/                # Visit, User
│  └─ views/                 # Admin HTML templates + layout
├─ app/plugins/              # Built‑in plugins (consent_banner, realtime, maps, visitors)
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
  window.TE_ENDPOINT = '/track-em/track.php';
</script>
<script async src="/track-em/assets/js/te.js"></script>
```
This sends a small JSON payload (`path`, `lang`, `screen` info). If `fetch()` fails, it falls back to a 1×1 GIF ping.

### Pixel‑only fallback (no JS)

```html
<img src="/track-em/track.php?p=/the/page&ts=<?=time()?>" alt="" width="1" height="1" style="position:absolute;left:-9999px;" />
```
Append `?debug=1` to `track.php` while testing to get JSON back instead of a GIF.

---

## Configuration

Copy `config/config.sample.php` → `config/config.php` and adjust. Full sample for reference:

```php
<?php
return array (
  'base_url' => '',
  'database' => 
  array (
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => '',
    'user' => '',
    'pass' => '',
  ),
  'theme' => 
  array (
    'active' => 'default',
  ),
  'i18n' => 
  array (
    'default' => 'en_US',
  ),
  'privacy' => 
  array (
    'respect_dnt' => true,
    'require_consent' => false,
    'ip_anonymize' => true,
    'ip_mask_bits' => 16,
  ),
  'geo' => [
  'enabled' => true,
  'provider'   => 'ip-api',
  'ip_api_base'=> 'http://ip-api.com/json'  // or point to your own HTTPS proxy
],
);

```

Key options (practical notes):

- `base_url`: The mount path (e.g., `''` when at web root, or `/track-em` in a subfolder). Affects asset links and redirects.
- `database`: MySQL/MariaDB connection — defaults live in code, but set these explicitly in production.
- `theme.active`: `"default"` or `"dark"` (runtime CSS is generated; placeholders exist under `/themes`).
- `i18n.default`: Default locale like `en_US`. Upload `i18n/{LOCALE}.php` files to add languages.
- `privacy`:
  - `respect_dnt` — Skip tracking when the browser's Do‑Not‑Track is on.
  - `require_consent` — Show/use the consent banner plugin and only track after "Allow".
  - `ip_anonymize` + `ip_mask_bits` — Masks the lower bits of IPs before storing.
- `geo`:
  - `enabled` — store lat/lon + city/country when available.
  - `provider` — `"ip-api"` (HTTP/HTTPS via proxy) or `"maxmind_local"`.
  - `ip_api_base` — Point to your own HTTPS proxy if you don't want direct HTTP calls.
  - `mm_license_key`, `mmdb_path` — For MaxMind local DB (auto‑download supported).
- `rate_limit`:
  - `enabled` — Throttle API endpoints.
  - `window_sec`, `max_events` — e.g., 60 seconds, 120 events.
- `retention.days` — Used by `scripts/retention_purge.php` to delete old rows.

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

---

## Plugins

Built‑in plugins ship under `app/plugins/` and are managed via the **Plugins** screen and API.

- `consent_banner` — drops a minimalist banner. Config keys:
- `message`, `position` (`top`|`bottom`).
- `visitors` — dashboard widget; no config.
- `realtime` — near‑real‑time updates via server polling; no config.
- `maps` — shows a map widget when geo is enabled.

Plugin assets are served via `?p=api.plugins.asset&plugin={id}&file=...`. Per‑plugin JSON configs are persisted under `storage/plugins/{id}/config.json` (the controller writes them atomically).

**Developing a plugin** (short version):

```
app/plugins/your_plugin/
├─ plugin.php         # optional bootstrap
├─ manifest.json      # optional metadata
└─ assets/...         # any JS/CSS/images
```

Your admin UI can fetch assets via `api.plugins.asset`. Read current config via `api.plugins.configs` and update with `api.plugins.config.set`.

---

## Geo options

- **ip-api.com** (default): simple web API. To avoid HTTP from the browser, set up an HTTPS reverse proxy and point `geo.ip_api_base` at it.
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
- Validate your locales (keys and placeholders) locally:

```bash
php cli/validate_locales.php
```

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

## License

See `LICENSE` if present. If absent, this copy is provided as‑is by the repository owner.

---

