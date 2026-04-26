# Anomaly Digest

`anomaly_digest` is a lightweight admin-only summary plugin.

It combines a few cheap traffic comparisons with optional summaries from existing plugins such as:

- `traffic_alerts`
- `bot_watch`
- `goals`
- `referrer_intel`

Storage:

- `storage/plugins/anomaly_digest/config.json`
- `storage/plugins/anomaly_digest/cache.json`

Notes:

- no public endpoints
- no background workers
- intended as a concise admin digest, not a monitoring system

Development notes:

- Route entry points include `anomaly_digest.admin` and `anomaly_digest.rebuild`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
