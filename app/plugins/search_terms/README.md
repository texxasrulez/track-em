# Search Terms Plugin

`search_terms` extracts lightweight internal-search summaries from tracked visit paths that include query strings.

## What It Does

- reads existing `visits.path` values only
- looks for configured query parameter names like `q`, `s`, `search`, `query`, or `term`
- reports aggregate top terms, top search paths, and a simple daily trend
- caches results in `storage/plugins/search_terms/cache.json`

## Storage

- defaults: `app/plugins/search_terms/config.json`
- saved config: `storage/plugins/search_terms/config.json`
- cache: `storage/plugins/search_terms/cache.json`

## Notes

- admin-only plugin
- no public endpoints
- no raw visitor identities are exposed
- terms are normalized and truncated before aggregation

## Development

For Track 'Em plugin conventions, routes, storage patterns, and admin UI guidance, see [docs/PLUGIN_DEVELOPMENT.md](../../../docs/PLUGIN_DEVELOPMENT.md).
