# KhabarSEO

Lightweight, configurable SEO for **news websites**. Built Persian-first for RTL publishers, and usable with any theme, any other plugin and any language.
No telemetry, no upsells, no external requests, no front-end CSS/JS.

> **Status: 0.2.0 beta (Phase 1, Part 1B).** Titles, meta descriptions, robots meta, canonical links and archive indexing rules are in place.
> Open Graph and X cards, head cleanup and the URL permanence guard arrive in Part 1C; sitemaps and structured data in later phases.

[فارسی](#خبرسئو-فارسی) · [Changelog](CHANGELOG.md) · [Contributing](CONTRIBUTING.md) · [Security](SECURITY.md)

## Requirements

| | |
|---|---|
| WordPress | 6.5 or newer (tested on 7.1.3) |
| PHP | 8.1 or newer, with `mbstring` (required), `dom` and `intl` (recommended). PHP 8.1 is already end of life and 8.2 ends on 31 December 2026: the Status page warns, please upgrade. |

## Install (no build step)

1. Download `khabar-seo-<version>.zip` from the releases.
2. WordPress admin: *Plugins, Add New, Upload Plugin*.
3. Activate. You are taken to the **setup wizard**.

## Safe takeover

KhabarSEO adds **nothing** to your pages until you finish the wizard, and it stays off while another SEO plugin is active.

* Run **Status, Scan now** *before* switching on: it lists the title, description, canonical, robots, Open Graph and JSON-LD tags your theme and plugins already output.
* `define( 'KHS_DISABLE', true );` in `wp-config.php` switches all output off at any time (kill switch).
* `define( 'KHS_FORCE_OUTPUT', true );` is a developer-only override of the "other SEO plugin detected" safeguard.
* Deactivating the plugin is always safe. Data is kept unless you untick **Advanced, Keep my data**.

## Titles and descriptions

Templates use these variables. On every context `%title%` is the name of what is shown (post title, term name, author name, date, search phrase, "Page not found").

| Variable | Meaning |
|---|---|
| `%title%` | Title of the item |
| `%sitename%`, `%tagline%` | Site name and tagline (Settings, General) |
| `%sep%` | The separator (`-`, `–`, `|` or `·`) |
| `%category%` | First category of a post, or the current category |
| `%author%` | Post author or archive author |
| `%date%` | Publication date as shown on the site (human-facing; may be in a local calendar such as Jalali, never used for machine-readable output) |
| `%page%` | "Page 2 of 5" beyond page 1, empty otherwise |
| `%excerpt%` | Written excerpt, start of the text (optional), term description or author biography |

Posts default to `%title% %sep% %sitename%`; lists to `%title% %sep% %page% %sep% %sitename%`. Empty variables never leave doubled separators.
Every post and term has its own SEO title, description, canonical and robots fields.

To keep a passage out of search snippets, use the **Hidden from search snippets** block or `[khs_nosnippet]…[/khs_nosnippet]` (`inline="1"` for a span). Both output `data-nosnippet`; this also hides the passage from normal snippets and AI features.

## Robots and canonical rules

* One robots tag: KhabarSEO merges into WordPress's own `wp_robots` filter. Default `index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1`.
* Search results are always `noindex, follow`; 404 pages `noindex`. Tags with fewer than 3 posts, date archives, empty author pages and attachment pages are `noindex` by default (all configurable).
* "Discourage search engines" in Settings, Reading is never overridden.
* One canonical on every indexable page: page N of a list and of a multi-page article points to itself (never to page 1), tracking parameters such as `utm_*` are removed, and URLs are normalized so Persian slugs are encoded the same way everywhere. Noindex pages get no canonical unless you enable it.
* Works with any permalink structure (plain, numeric, date-based, `%postname%`, `%category%`, with or without trailing slash) and subdirectory installs.

## Jalali-safe dates

Machine-readable dates are built from stored GMT timestamps with `DateTimeImmutable`, never through `wp_date()`, `date_i18n()` or `get_the_date()`, so calendar plugins cannot change them. The Status page compares the emitted ISO value with the stored time.

## Privacy and performance

* No tracking, telemetry, remote notices or auto-updater. The only HTTP request is the admin-triggered loopback scan of **your own** site.
* One autoloaded option (`khs_settings`, hard cap 8 KB); bulky data lives in `khs_settings_ext` (not autoloaded).
* Measured on WordPress 7.1.3 without an object cache: **0 additional database queries** on a single post, the home page and search; **1** on a category page (term overrides).

## Cache and CDN integration

KhabarSEO contains no cache-specific code. Hooks for integrations: `khs_settings_updated` (action, after settings change), `khs_title`, `khs_description`, `khs_canonical` and `khs_robots_directives` (filters). Purge your page cache after changing settings.

## Development

```bash
composer install            # dev tools only; the plugin has no runtime dependencies
composer lint               # php -l on every file
composer smoke              # dependency-free tests (php tests/smoke.php)
composer phpcs              # WordPress Coding Standards
composer generality         # no theme or plugin names in runtime code
php bin/make-pot.php        # regenerate languages/khabar-seo.pot
php bin/merge-po.php        # merge it into languages/*.po
php bin/build-mo.php        # compile .po to .mo (no gettext needed)
bin/build-zip               # dist/khabar-seo-<version>.zip (install-ready plugin)
bin/build-repo-zip          # dist/khabar-seo-repo-<version>.zip (repository snapshot)
```

The Persian translation (`languages/khabar-seo-fa_IR.po` and the compiled `.mo`) is part of the repository so Persian sites get a Persian admin automatically.

## Credits and license

Copyright (C) 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors.

KhabarSEO is free software, licensed under the GNU General Public License version 2 or (at your option) any later version. See [LICENSE](LICENSE).
Repository: https://github.com/MohammadToday/KhabarSEO

---

<div dir="rtl" lang="fa">

## خبرسئو (فارسی)

**KhabarSEO** افزونهٔ سبک و قابل‌تنظیم سئو برای **سایت‌های خبری** است. اولویت آن رسانه‌های فارسی و راست‌به‌چپ است، اما با هر پوسته، هر افزونهٔ دیگر و هر زبانی کار می‌کند.
بدون ردیابی، بدون تبلیغ، بدون درخواست بیرونی و بدون CSS/JS در سمت بازدیدکننده.

> **وضعیت: نسخهٔ بتای ۰٫۲٫۰ (فاز ۱، بخش ۱B).** عنوان، توضیحات متا، robots، canonical و قواعد ایندکس بایگانی‌ها آماده است. Open Graph، کارت X، پاک‌سازی head و محافظ نشانی در بخش ۱C می‌آیند.

### نصب

۱. فایل `khabar-seo-<version>.zip` را دانلود کنید. ۲. در پیشخوان: افزونه‌ها ← افزودن ← بارگذاری افزونه. ۳. فعال کنید؛ راهنمای راه‌اندازی باز می‌شود.

### جایگزینی ایمن

تا پایان راهنمای راه‌اندازی هیچ چیزی به صفحه‌های شما اضافه نمی‌شود و تا وقتی افزونهٔ سئوی دیگری فعال است، خاموش می‌ماند.
پیش از روشن کردن، از **وضعیت ← همین حالا بررسی کن** استفاده کنید تا ببینید پوسته و افزونه‌ها چه برچسب‌هایی تولید می‌کنند.
افزودن `define( 'KHS_DISABLE', true );` به `wp-config.php` هر زمان همهٔ خروجی را خاموش می‌کند.

### عنوان و توضیحات

الگوها از متغیرهای `%title%`، `%sitename%`، `%tagline%`، `%sep%`، `%category%`، `%author%`، `%date%`، `%page%` و `%excerpt%` استفاده می‌کنند. پیش‌فرض نوشته‌ها `%title% %sep% %sitename%` است (اول تیتر، آخر نام سایت).
`%date%` تاریخ انسانی است و ممکن است جلالی باشد؛ هرگز برای خروجی ماشین‌خوان استفاده نمی‌شود.
برای پنهان کردن یک بخش از بُرش‌های جست‌وجو، از بلوک «پنهان از بُرش‌های جست‌وجو» یا `[khs_nosnippet]…[/khs_nosnippet]` استفاده کنید.

### robots و canonical

همیشه فقط یک برچسب robots و یک canonical. نتایج جست‌وجوی داخلی همیشه `noindex, follow` است. صفحهٔ ۲ به بعد هر فهرست و هر مقالهٔ چندصفحه‌ای به خودش canonical می‌دهد، نه به صفحهٔ ۱. پارامترهای ردیابی مثل `utm_*` حذف می‌شوند و نامک‌های فارسی همه‌جا یکسان رمزگذاری می‌شوند.

### تاریخ جلالی

تاریخ‌های ماشین‌خوان هرگز از `wp_date()`، `date_i18n()` یا `get_the_date()` عبور نمی‌کنند؛ KhabarSEO تاریخ را از زمان GMT ذخیره‌شده می‌سازد.

### اعتبار و مجوز

Copyright (C) 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors.

این افزونه نرم‌افزار آزاد است و تحت مجوز GNU GPL نسخهٔ ۲ یا (به انتخاب شما) هر نسخهٔ بعدی منتشر می‌شود. متن کامل در فایل [LICENSE](LICENSE) است.

</div>
