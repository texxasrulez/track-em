# Event Tracking Plugin

`event_tracking` adds lightweight named event collection to Track 'Em.

What it tracks:

- Simple event names such as `download_pdf`, `video_play`, or `outbound_link`.
- Optional short labels.
- Optional short metadata pairs that pass server-side filtering.

What it does not track:

- Form values
- Passwords
- Textarea contents
- Large payloads
- Browser fingerprints

Storage:

- Events are written as append-only JSONL files in `storage/plugins/event_tracking/events-YYYY-MM.jsonl`.

Client usage:

```html
<script async src="/track-em/index.php?p=event_tracking.asset&file=trackem-events.js"></script>
<script>
  window.trackemEvent && window.trackemEvent("download_pdf", {
    label: "brochure",
    meta: { type: "pdf" }
  });
</script>
```

Declarative usage:

```html
<a href="/file.pdf"
   data-trackem-event="download_pdf"
   data-trackem-label="Brochure"
   data-trackem-meta-type="pdf">
   Download PDF
</a>
```

Development notes:

- Route entry points include `event_tracking.admin`, `event_tracking.asset`, and `event_tracking.collect`
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
