# Plugin Health

`plugin_health` is a lightweight admin-only diagnostics plugin for the Track Em plugin system.

It checks:

- plugin manifest readability
- controller presence
- storage directory existence and writability
- saved config, cache, state, and rollup JSON readability
- stale plugin caches
- recent plugin-side delivery or scheduler errors
- a few simple dependency expectations such as event-based goals requiring `event_tracking`

Storage:

- `storage/plugins/plugin_health/config.json`
- `storage/plugins/plugin_health/cache.json`

Notes:

- no public endpoints
- no history scanning
- intended for maintenance and diagnostics, not deep testing

Development notes:

- route entry points include `plugin_health.admin` and `plugin_health.rebuild`
- full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
