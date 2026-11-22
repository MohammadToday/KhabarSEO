# KhabarSEO

Lightweight, configurable SEO for **news websites**, built for Persian and other right-to-left publishers (works for any language).
No telemetry, no upsells, no external requests, no front-end CSS/JS.

> **Status: 0.1.0 beta, Phase 1 / Part 1A.** This release contains the framework, setup wizard, settings panel, conflict scanner and Status page.
> It does **not** output any tags yet: titles, robots, canonical and Open Graph arrive in Parts 1B and 1C.
> Replace `OWNER` in the plugin header, `readme.txt` and issue links with your GitHub account or organization.

[فارسی](#خبرسئو-فارسی) · [Changelog](CHANGELOG.md) · [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md)

## Requirements

| | |
|---|---|
| WordPress | 6.5 or newer (tested on 7.1.3) |
| PHP | 8.1 or newer, with `mbstring` (required), `dom` and `intl` (recommended). PHP 8.1 is already end of life: the Status page warns, please upgrade. |

## Install (no build step)

1. Download the release zip (or use *Code → Download ZIP* and rename the folder to `khabar-seo`).
2. WordPress admin → *Plugins → Add New → Upload Plugin*.
3. Activate. You are taken to the **setup wizard**.

## Safe takeover

KhabarSEO adds **nothing** to your pages until you finish the wizard, and it stays off while another SEO plugin is active.

* Run **Status → Scan now** *before* switching on: it lists the tags your theme and plugins already output and, for known themes, which theme option to turn off.
* `define( 'KHS_DISABLE', true );` in `wp-config.php` switches all output off at any time (kill switch).
* `define( 'KHS_FORCE_OUTPUT', true );` is a developer-only override of the "other SEO plugin detected" safeguard.
* Deactivating the plugin is always safe. Data is kept unless you untick **Advanced → Keep my data**.

## What is in Part 1A

* One central **settings schema**: the panel, wizard, validation, defaults, export/import and reset all derive from it.
* Every choice shows a **Recommended** badge and a one- or two-sentence plain-language guide.
* **Setup wizard** (publication name, language, news post types, archive indexing, separator, social defaults) ending in the *News site, recommended* preset.
* **Conflict scanner**: detects other SEO plugins; parses the *rendered* `<head>` through an admin-only loopback request.
* **Status page** with *Copy diagnostics* (no URLs or secrets): PHP and WordPress versions with end-of-life warnings, timezone check (January/July offsets), date-conversion check, permalink and install type.
* **Jalali safety**: machine-readable dates are built from stored GMT timestamps with `DateTimeImmutable`, never through `wp_date()`, `date_i18n()` or `get_the_date()`.
  Verified against the WP-Parsidate source (v6.6.0): with the `fa_IR` locale it converts those functions, even `c`/ISO format.
* Helpers used by later parts: `Url::normalize()` (one URL normalizer, Persian slugs encoded identically everywhere), `Text::truncate()` (UTF-8 and ZWNJ safe), `Time::iso_from_gmt()`.

## Privacy and performance

* No tracking, no telemetry, no remote notices, no auto-updater. The only HTTP request is the admin-triggered loopback scan of **your own** site.
* One autoloaded option (`khs_settings`, hard cap 8 KB); bulky data lives in `khs_settings_ext` (not autoloaded).
* Measured on WordPress 7.1.3: **0 additional database queries** on the home page and on a single post.

## Development

```bash
composer install            # dev tools only; the plugin has no runtime dependencies
composer lint               # php -l on every file
composer smoke              # dependency-free tests (php tests/smoke.php)
composer phpcs              # WordPress Coding Standards
php bin/make-pot.php        # regenerate languages/khabar-seo.pot
php bin/build-mo.php        # compile languages/*.po to .mo (no gettext needed)
bin/build-zip               # dist/khabar-seo-<version>.zip
```

The Persian translation (`languages/khabar-seo-fa_IR.po` and the compiled `.mo`) is part of the repository so Persian sites get a Persian admin automatically.
See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

---

<div dir="rtl" lang="fa">

## خبرSEO (فارسی)

**KhabarSEO** افزونهٔ سبک و قابل‌تنظیم سئو برای **سایت‌های خبری** است؛ برای رسانه‌های فارسی و راست‌به‌چپ ساخته شده، اما برای هر زبانی کار می‌کند.
بدون ردیابی، بدون تبلیغ، بدون درخواست بیرونی و بدون CSS/JS در سمت بازدیدکننده.

> **وضعیت: نسخهٔ بتای ۰٫۱٫۰، فاز ۱ بخش ۱A.** این نسخه چارچوب افزونه، راهنمای راه‌اندازی، پنل تنظیمات، اسکنر تداخل و صفحهٔ وضعیت را دارد. هنوز هیچ برچسبی در صفحه‌ها نمی‌گذارد؛ عنوان، robots، canonical و Open Graph در بخش‌های ۱B و ۱C می‌آیند.

### نصب

۱. فایل zip را دانلود کنید. ۲. در پیشخوان: افزونه‌ها ← افزودن ← بارگذاری افزونه. ۳. فعال کنید؛ راهنمای راه‌اندازی باز می‌شود.

### جایگزینی ایمن

تا پایان راهنمای راه‌اندازی هیچ چیزی به صفحه‌های شما اضافه نمی‌شود و تا وقتی افزونهٔ سئوی دیگری فعال است، خاموش می‌ماند.
پیش از روشن کردن، از **وضعیت ← همین حالا بررسی کن** استفاده کنید تا ببینید پوسته و افزونه‌ها چه برچسب‌هایی تولید می‌کنند.
افزودن `define( 'KHS_DISABLE', true );` به `wp-config.php` هر زمان همهٔ خروجی را خاموش می‌کند.

### تاریخ جلالی

تاریخ‌های ماشین‌خوان هرگز از `wp_date()`، `date_i18n()` یا `get_the_date()` عبور نمی‌کنند. با بررسی کد WP-Parsidate (نسخهٔ ۶٫۶٫۰) مشخص شد که در زبان `fa_IR` این توابع را حتی برای قالب ISO تبدیل می‌کند؛ KhabarSEO تاریخ را از زمان GMT ذخیره‌شده می‌سازد.

### مجوز

GPL-2.0-or-later

</div>
