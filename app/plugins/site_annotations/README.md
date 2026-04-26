# Site Annotations

`site_annotations` is a lightweight admin-only plugin for recording dated notes such as:

- deploys
- campaigns
- outages
- content launches
- general notes

Storage:

- `storage/plugins/site_annotations/config.json`

Notes:

- no public endpoints
- no background workers
- bounded annotation storage
- sanitized annotation text only

Development notes:

- Route entry point: `site_annotations.admin`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
