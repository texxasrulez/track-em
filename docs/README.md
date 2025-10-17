# Track Em — Root-as-Webroot Build

- Point your web server at the **project root** (this directory).
- Visit `/install.php` to configure DB, write `config/config.php`, seed the admin, and lock the installer.
- App entry is `/index.php`; tracker endpoint is `/track.php`.
- Embed snippet:
  ```html
  <script src="/assets/js/te.js" defer></script>
  ```
