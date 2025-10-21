# Locale Translation (DeepL)

CLI helper to fill in missing or changed keys using DeepL.

## Usage

```bash
export DEEPL_API_KEY=xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
php scripts/translate_locales.php --source=en_US           # process all
php scripts/translate_locales.php --only=fr_FR,es_ES       # restrict
php scripts/translate_locales.php --dry-run                # no writes
php scripts/translate_locales.php --force                  # re-translate all keys
```

What it does:
- Loads `i18n/en_US.php` (default) as the source of truth.
- Compares the current source strings against the last-run checksum (`i18n/.mt/mt_state.json`).
- For each target locale file (`i18n/*.php`), calls DeepL **only** for missing keys or keys where the source English changed since the last run.
- Skips locales not supported by DeepL (auto-detected).
- Preserves placeholders like `{name}`, `%s`, `%1$d`.

Outputs:
- Updated `i18n/<locale>.php` files (only if there are changes).
- `i18n/.mt/mt_report.json` with a summary.
- `i18n/.mt/mt_state.json` with source checksums for change detection.
