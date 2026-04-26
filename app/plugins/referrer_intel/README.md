# Referrer Intel Plugin

`referrer_intel` turns raw visit referrers into lightweight source reporting.

It classifies traffic into:

- `direct`
- `internal`
- `search`
- `social`
- `external`
- `unknown`

Storage:

- Config: `storage/plugins/referrer_intel/config.json`
- Cached rollups: `storage/plugins/referrer_intel/rollups.json`

Privacy defaults:

- Domain-only reporting by default
- Full referrer paths hidden by default
- Query strings stripped unless explicitly enabled

Development notes:

- Route entry point: `referrer_intel.admin`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
