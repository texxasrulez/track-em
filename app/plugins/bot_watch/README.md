# Bot Watch

`bot_watch` is a Track Em plugin that flags likely bot, scraper, and probing behavior for admin review.

It is detection-only:
- no public endpoints
- no blocking
- no external services

Storage:
- `storage/plugins/bot_watch/config.json`
- `storage/plugins/bot_watch/state.json`
- `storage/plugins/bot_watch/detections.jsonl`

Signals used:
- high hits per minute from the same source
- many unique paths in a short window
- common probe paths like `wp-login.php`, `xmlrpc.php`, `.env`, and `phpmyadmin`
- empty, weird, or scanner-style user-agents
- repeated 404s when a `status` column exists in `visits`

The detection log is intentionally structured so a future blocking or firewall-style plugin can consume it without changing this plugin into an active blocker.

Development notes:

- Route entry point: `bot_watch.admin`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
