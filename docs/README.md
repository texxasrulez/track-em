# Track Em Docs

Main docs:

- [../README.md](../README.md) - project overview, setup, routes, operations, and plugin summary
- [PLUGIN_DEVELOPMENT.md](PLUGIN_DEVELOPMENT.md) - detailed third-party plugin development guide
- [I18N.md](I18N.md) - localization notes
- [TRANSLATION.md](TRANSLATION.md) - translation workflow

Root-as-webroot quick notes:

- Point your web server at the project root.
- Visit `/install.php` to configure DB, write `config/config.php`, seed the admin, and lock the installer.
- App entry is `/index.php`; tracker endpoint is `/track.php`.
- Embed snippet:

```html
<script src="/assets/js/te.js" defer></script>
```
