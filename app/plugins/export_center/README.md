# Export Center

`export_center` is a lightweight admin-only export plugin.

It generates cached JSON and CSV snapshots under:

- `storage/plugins/export_center/config.json`
- `storage/plugins/export_center/exports/`

Supported export sections:

- traffic summary
- top paths
- referrer summary when `referrer_intel` exists
- event summary when `event_tracking` exists
- goals summary when `goals` exists

Notes:

- no public endpoints
- downloads are admin-only through `export_center.download`
- designed for lightweight snapshots, not raw bulk history export

Development notes:

- route entry points include `export_center.admin`, `export_center.generate`, and `export_center.download`
- full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
