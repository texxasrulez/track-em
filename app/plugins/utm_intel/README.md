# UTM Intel

`utm_intel` is a lightweight admin-only reporting plugin for campaign-style query parameters.

It reads tracked visit paths, extracts values such as:

- `utm_source`
- `utm_medium`
- `utm_campaign`
- `utm_content`
- `utm_term`

Then it shows cached aggregate summaries for:

- top sources
- top mediums
- top campaigns
- top source / medium pairs
- recent sanitized examples

Storage:

- `storage/plugins/utm_intel/config.json`
- `storage/plugins/utm_intel/cache.json`

Notes:

- no public endpoints
- no external services
- no schema changes
- intended for lightweight campaign visibility, not full attribution

Development notes:

- Route entry points include `utm_intel.admin` and `utm_intel.rebuild`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
