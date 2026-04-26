# Geo Intel

`geo_intel` is a lightweight admin-only geo summary plugin.

It uses existing `country` and optional `city` values already stored in `visits` and produces cached aggregate reporting for:

- top countries
- optional top cities
- simple trend by day

Storage:

- `storage/plugins/geo_intel/config.json`
- `storage/plugins/geo_intel/cache.json`

Notes:

- no public endpoints
- no external services
- no schema changes
- country-first reporting is the default, with city summaries off by default

Development notes:

- route entry points include `geo_intel.admin` and `geo_intel.rebuild`
- full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
