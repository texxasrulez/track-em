# Scheduler

`scheduler` is a lightweight admin-triggered plugin scheduler.

It stores:

- `storage/plugins/scheduler/config.json`
- `storage/plugins/scheduler/state.json`
- `storage/plugins/scheduler/runs.jsonl`

What it does:

- keeps a curated list of low-cost plugin maintenance jobs
- can run due jobs when an admin opens the Scheduler page
- can also run due jobs or all active jobs manually
- records last run state and a small recent run log

Notes:

- no background daemon
- no public endpoints
- intended for plugin maintenance like cache refreshes and report generation
- uses direct plugin service calls for a small known job catalog

Development notes:

- route entry points include `scheduler.admin`, `scheduler.run_due`, and `scheduler.run_all`
- full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
