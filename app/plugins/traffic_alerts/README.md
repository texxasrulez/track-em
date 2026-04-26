# Traffic Alerts Plugin

`traffic_alerts` adds lightweight admin alerts for unusual traffic patterns.

Storage:

- `storage/plugins/traffic_alerts/config.json`
- `storage/plugins/traffic_alerts/state.json`
- `storage/plugins/traffic_alerts/alerts.jsonl`

Checks are triggered opportunistically from admin/plugin/dashboard activity. There is no daemon or always-running worker.

Development notes:

- Route entry points include `traffic_alerts.admin`, `traffic_alerts.rebuild`, and `traffic_alerts.dashboard_data`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
