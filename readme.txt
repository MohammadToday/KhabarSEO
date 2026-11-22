=== KhabarSEO ===
Contributors: mohammadtoday
Tags: seo, news, persian, rtl, jalali
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, configurable SEO for news websites. Persian-first for RTL publishers, usable in any language. No telemetry, no upsells.

== Description ==

KhabarSEO is an SEO plugin for **news publishers**. Persian (fa_IR) and other right-to-left sites come first, through locale-driven defaults, but it works with any theme, any other plugin and any language. Everything is configurable from the settings panel, and every choice shows a "Recommended" badge with a short plain-language guide.

**This is a beta (0.2.0, Phase 1 Part 1B).** It controls titles, meta descriptions, robots meta and canonical links. Open Graph and X cards arrive in 0.3.

= Safe by default =

* Adds nothing to your pages until you finish the setup wizard.
* Stays off while another SEO plugin is active, so you never get duplicate tags.
* A `KHS_DISABLE` constant in wp-config.php switches all output off at any time.
* Status, Scan now lists the SEO tags your theme and plugins already output.

= Titles, descriptions, robots, canonical =

* Title and description templates for every content type, taxonomy, the home page, archives, search and 404 pages, with per-post and per-term overrides.
* Exactly one robots tag (merged into WordPress's own), default `index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1`.
* Archive rules with recommended defaults: thin tags, date archives, empty author pages and attachment pages are noindex; internal search is always noindex, follow.
* Exactly one canonical: page 2 and later point to themselves, tracking parameters are removed, Persian slugs are encoded consistently.
* A "Hidden from search snippets" block and shortcode (data-nosnippet).

= Built for Persian sites =

* Machine-readable dates are built from stored GMT timestamps and never pass through Jalali conversion filters.
* UTF-8 and ZWNJ (half-space) safe truncation of descriptions.
* Complete Persian translation included.

= Privacy and performance =

* No tracking, telemetry, remote notices, auto-updater, or front-end CSS and JavaScript.
* The only HTTP request is an admin-triggered request to your own site (the head scan).
* One autoloaded option, capped at 8 KB. No additional database queries on single posts.

== Installation ==

1. Upload the plugin zip via Plugins, Add New, Upload Plugin, or extract it into `wp-content/plugins/khabar-seo`.
2. Activate it and follow the setup wizard.
3. Open KhabarSEO, Status and click "Scan now" to see what your theme already outputs.

== Frequently Asked Questions ==

= Another SEO plugin is active. What happens? =

KhabarSEO stays switched off and shows a notice. Deactivate the other plugin first, then finish the wizard.

= How do I switch everything off in an emergency? =

Add `define( 'KHS_DISABLE', true );` to wp-config.php.

= My theme prints the site name twice in the title. =

Older themes without title-tag support print their own title with wp_title(). KhabarSEO leaves those titles alone by default; see Advanced, Theme compatibility.

= Does it work with Jalali date plugins? =

Yes. Such plugins convert wp_date(), date_i18n() and get_the_date(). KhabarSEO never uses those functions for machine-readable dates. The `%date%` title variable is human-facing and may show the local calendar.

== Changelog ==

= 0.2.0 =
* Titles, meta descriptions, robots meta and canonical links with templates and per-post and per-term overrides.
* Archive indexing rules, attachment page redirects, data-nosnippet block and shortcode.

= 0.1.0 =
* Initial beta: framework, settings schema, setup wizard, conflict scanner, Status page, Persian translation.

== Upgrade Notice ==

= 0.2.0 =
KhabarSEO now outputs titles, descriptions, robots and canonical tags once the setup wizard is finished. Run Status, Scan now after updating.

== Credits ==

Copyright (C) 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors.
Source code: https://github.com/MohammadToday/KhabarSEO
