# Funnel Reports

`funnel_reports` is a lightweight admin-only plugin for aggregate funnel reporting.

It stores configuration in:

- `storage/plugins/funnel_reports/config.json`
- `storage/plugins/funnel_reports/cache.json`

Each funnel is defined as simple line-based steps:

- `Name|contains_path|/checkout`
- `Purchase|exact_path|/thank-you`
- `Signup Submit|event_name|signup_submit|footer`

Notes:

- uses aggregate step counts over the selected range
- does not reconstruct individual sessions or user journeys
- event steps require the `event_tracking` plugin
- no public endpoints

Development notes:

- route entry points include `funnel_reports.admin` and `funnel_reports.rebuild`
- full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
