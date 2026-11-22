# Contributing

Thanks for helping. KhabarSEO is a public plugin for news sites; nothing may be hardcoded to one site, theme, host or locale.

## Ground rules

* PHP **8.1 floor**. Do not use 8.2+-only syntax (readonly classes, `true`/`false`/`null` standalone types, DNF types, ...). CI runs 8.1 to 8.5.
* WordPress Coding Standards (`composer phpcs`). Escape on output, sanitize on input, check capability and nonce on every handler.
* English source strings; text domain `khabar-seo`; **string literals only** in gettext calls (the extractor and PHPCS need them).
* No external HTTP requests, no telemetry, no promos, no front-end CSS/JS by default.
* Machine-readable dates must never go through `wp_date()`, `date_i18n()` or `get_the_date()`. Use `KhabarSEO\Core\Time`.
* Any Google-dependent behavior must cite the official page it was checked against, or be marked `[VERIFY]` and kept behind a setting.

## Setup

```bash
composer install
composer lint && composer smoke && composer phpcs
```

## Translations

```bash
php bin/make-pot.php     # update languages/khabar-seo.pot
# edit languages/khabar-seo-fa_IR.po (or add a new <locale>.po)
php bin/build-mo.php     # compile; commit the .mo so zip installs work without a build
```

## Theme and plugin compatibility reports

Open a "Theme compatibility report" issue with the **Status → Copy diagnostics** output and the result of **Scan now**.
To add a hint, extend `data/conflicts.php`. Set `verified_on` to the date you checked it against the vendor's documentation or source, or leave it `null` if it is from memory.

## Pull requests

Keep them focused, update `CHANGELOG.md`, and add a case to `tests/smoke.php` for any pure logic you touch.
