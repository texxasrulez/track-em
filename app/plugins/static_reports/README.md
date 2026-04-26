# Static Reports

`static_reports` generates cached admin-only HTML reports for Track Em.

Storage:
- `storage/plugins/static_reports/config.json`
- `storage/plugins/static_reports/state.json`
- `storage/plugins/static_reports/reports/`

Behavior:
- lists cached reports from disk in the plugin admin UI
- generates reports manually with `Generate Now`
- can opportunistically generate due daily and weekly reports once per period
- can optionally email generated report notifications to a configured address
- serves reports through an admin-only plugin route

Included sections can pull from these optional plugins when they are installed:
- `referrer_intel`
- `event_tracking`
- `goals`
- `bot_watch`

Reports are sanitized HTML snapshots intended for admin review, not public sharing.

Development notes:

- Route entry points include `static_reports.admin`, `static_reports.generate`, and `static_reports.view`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
