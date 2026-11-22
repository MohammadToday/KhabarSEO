# Contributing

Thanks for helping. KhabarSEO is a general-purpose plugin for news websites: it must work with any theme, any other plugin and any language.
Persian (and other RTL) sites come first, through locale-driven defaults and settings, never through hardcoding.

By contributing you agree that your contribution is licensed under GPL-2.0-or-later, the license of this project (inbound = outbound, no CLA).

## Ground rules

* PHP **8.1 floor**. Do not use 8.2+-only syntax (readonly classes, standalone `true`/`false`/`null` types, DNF types, ...). CI runs PHP 8.1 to 8.5.
* WordPress Coding Standards (`composer phpcs`). Escape on output, sanitize on input, check capability and nonce on every handler.
* English source strings; text domain `khabar-seo`; **string literals only** in gettext calls. Template variables such as `%title%` are passed in with `sprintf()`, never written inside a translatable string.
* No external HTTP requests, no telemetry, no promos, no front-end CSS/JS by default, no credit links.
* Machine-readable dates must never go through `wp_date()`, `date_i18n()` or `get_the_date()`. Use `KhabarSEO\Core\Time`.
* No logic may name a specific theme or plugin. Hints live only in `data/conflicts.php` (with `verified_on`), docs and tests. `bin/check-generality` enforces this in CI.
* Any Google-dependent behavior must cite the official page it was checked against, or be marked `[VERIFY]` and kept behind a setting.

## Setup

```bash
composer install
composer lint && composer smoke && composer phpcs && composer generality
```

## Translations

```bash
php bin/make-pot.php     # update languages/khabar-seo.pot
php bin/merge-po.php     # merge new strings into every languages/*.po (keeps translations)
# translate the empty msgstr entries
php bin/build-mo.php     # compile; commit the .mo so zip installs work without a build
```

## Theme and plugin compatibility reports

Open a "Theme compatibility report" issue with the **Status, Copy diagnostics** output and the result of **Scan now**.
To add a hint, extend `data/conflicts.php`. Set `verified_on` to the date you checked it against the vendor's documentation or source, or `null` if it is from memory.

## Pull requests

Keep them focused, update `CHANGELOG.md`, and add a case to `tests/smoke.php` for any pure logic you touch.
