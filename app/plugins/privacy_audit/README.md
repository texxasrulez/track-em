# Privacy Audit

`privacy_audit` is a lightweight admin-only plugin that inspects Track Em configuration and selected plugin settings for privacy-sensitive patterns.

Current checks include examples like:

- IP anonymization disabled
- weak IP mask settings
- Do-Not-Track disabled
- insecure geo lookup allowance
- referrer query string reporting enabled
- static reports private detail enabled
- public widget privacy thresholds below the safer baseline

Storage:

- `storage/plugins/privacy_audit/config.json`
- `storage/plugins/privacy_audit/cache.json`

Notes:

- no public endpoints
- no background workers
- config-driven only
- intended as a heuristic helper, not a full compliance or legal audit

Development notes:

- Route entry points include `privacy_audit.admin` and `privacy_audit.rebuild`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
