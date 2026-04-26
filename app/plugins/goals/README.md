# Goals Plugin

`goals` adds lightweight conversion reporting on top of Track 'Em visits and optional `event_tracking` events.

Supported goal types:

- `path_match`: wildcard path matching such as `/checkout*`
- `exact_path`: exact page path matching
- `contains_path`: substring match against a path
- `event_name`: event name matches from the `event_tracking` plugin

Storage:

- Config: `storage/plugins/goals/config.json`
- Cached report rollups: `storage/plugins/goals/rollups.json`

Notes:

- Conversion rate is based on visits because Track 'Em does not currently expose a separate session model here.
- No IP addresses or private visitor identifiers are shown in the goals UI.

Development notes:

- Route entry point: `goals.admin`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
