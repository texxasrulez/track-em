# Public Widgets Plugin

`public_widgets` adds privacy-preserving public embeds for normal HTML pages.

Use the admin Plugins screen to:

- Enable or disable the public hit counter.
- Choose site-wide or per-path counting.
- Choose text or image-digit counter rendering.
- Upload digit theme ZIP files containing `0.png` through `9.png`.
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

Counter display modes:

- `Text counter` keeps the original text output, such as `1.2k Visits`.
- `Image digit counter` renders digits as PNG images through the plugin-owned digit endpoint and falls back quietly to text if a theme cannot be resolved.

Digit theme upload rules:

- Upload a ZIP file containing exactly `0.png` through `9.png` at the ZIP root.
- No nested folders, no extra files, no SVG, and no renamed non-image files.
- Recommended transparent PNG size is roughly 24px to 64px tall.
- Maximum ZIP size is 2 MB.
- Maximum image size is 100 KB per digit, with maximum dimensions of 128x128 pixels.

Digit image endpoint example:

```text
/track-em/index.php?p=public_widgets.digit&id=default&n=7
```

Uploaded digit themes are stored under:

```text
storage/plugins/public_widgets/digit_themes/{theme_id}/
```

Built-in themes may live in the plugin assets directory, but uploaded themes are never written into plugin source files.

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
