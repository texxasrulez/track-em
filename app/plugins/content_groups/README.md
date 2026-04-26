# Content Groups

`content_groups` is a lightweight admin-only plugin for grouping paths into named buckets such as:

- `/blog/*`
- `/docs/*`
- `/pricing`
- `/shop/*`

Features:

- wildcard, exact, contains, and prefix matching
- first-match-wins rule order
- lightweight grouped reporting over existing visit paths
- cached report output

Storage:

- `storage/plugins/content_groups/config.json`
- `storage/plugins/content_groups/cache.json`

Notes:

- no public endpoints
- no background workers
- no new tracking behavior

Development notes:

- Route entry points include `content_groups.admin` and `content_groups.rebuild`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
