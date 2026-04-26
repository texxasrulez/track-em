# Public Widgets Plugin

`public_widgets` adds privacy-preserving public embeds for normal HTML pages.

Use the admin Plugins screen to:

- Enable or disable the public hit counter.
- Choose site-wide or per-path counting.
- Configure a public visitor map profile.
- Copy the generated counter and iframe snippets.

Privacy rules:

- Public endpoints only return aggregated counts or aggregated map buckets.
- They never expose IP addresses, visit ids, exact timestamps, referrers, user agents, or individual raw coordinates.
- The map requires a minimum bucket size before a point is shown.

Counter embed example:

```html
<span data-trackem-counter="site"></span>
<script async src="/track-em/index.php?p=api.plugins.asset&key=public_widgets&file=assets/counter.js"></script>
```

Map embed example:

```html
<iframe
  src="/track-em/index.php?p=public_widgets.map_embed&id=main"
  width="100%"
  height="520"
  loading="lazy"
  referrerpolicy="no-referrer-when-downgrade"
  style="border:0; border-radius:12px; overflow:hidden;"
  title="Visitor Map">
</iframe>
```

Development notes:

- Route entry points include `public_widgets.admin`, `public_widgets.map_embed`, and plugin-owned public data routes
- Full plugin conventions: [`docs/PLUGIN_DEVELOPMENT.md`](../../../docs/PLUGIN_DEVELOPMENT.md)
