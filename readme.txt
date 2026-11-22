=== KhabarSEO ===
Contributors: khabarseo
Tags: seo, news, persian, rtl, jalali
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight, configurable SEO for news websites, built for Persian and other RTL publishers. No telemetry, no upsells.

== Description ==

KhabarSEO is an SEO plugin for **news publishers**. It is designed for Persian (fa_IR) and other right-to-left sites but works for any language. Nothing is hardcoded to a site, theme or host: everything is configurable from the settings panel, and every choice shows a "Recommended" badge with a short plain-language guide.

**This is a beta (0.1.0, Phase 1 Part 1A).** It contains the framework, setup wizard, settings panel, conflict scanner and Status page. It does not output titles, robots, canonical or Open Graph tags yet.

= Safe by default =

* Adds nothing to your pages until you finish the setup wizard.
* Stays off while another SEO plugin is active, so you never get duplicate tags.
* A `KHS_DISABLE` constant in wp-config.php switches all output off at any time.
* Status, Scan now lists the SEO tags your theme and plugins already output, before you switch KhabarSEO on.

= Built for Persian sites =

* Machine-readable dates are built from stored GMT timestamps and never pass through Jalali conversion filters.
* A checker compares ISO dates with the stored time and verifies the timezone offsets (Iran has no daylight saving time since 2022).
* UTF-8 and ZWNJ (half-space) safe text truncation; consistent encoding of Persian slugs in URLs.
* Complete Persian translation included.

= Privacy and performance =

* No tracking, no telemetry, no remote notices, no auto-updater, no front-end CSS or JavaScript.
* The only HTTP request is an admin-triggered request to your own site (the head scan).
* One autoloaded option, capped at 8 KB. Zero additional database queries on public pages.

== Installation ==

1. Upload the plugin zip via Plugins, Add New, Upload Plugin, or extract it into `wp-content/plugins/khabar-seo`.
2. Activate it and follow the setup wizard.
3. Open KhabarSEO, Status and click "Scan now" to see what your theme already outputs.

== Frequently Asked Questions ==

= Why does it output nothing yet? =

This is Part 1A of the first phase: the framework. Titles, robots, canonical and Open Graph arrive in the next parts.

= Another SEO plugin is active. What happens? =

KhabarSEO stays switched off and shows a notice. Deactivate the other plugin first, then finish the wizard.

= How do I switch everything off in an emergency? =

Add `define( 'KHS_DISABLE', true );` to wp-config.php.

= Does it work with WP-Parsidate? =

Yes. WP-Parsidate converts wp_date(), date_i18n() and get_the_date() on fa_IR sites. KhabarSEO never uses those functions for machine-readable dates.

== Changelog ==

= 0.1.0 =
* Initial beta: framework, settings schema, setup wizard, conflict scanner, Status page, Persian translation.

== Upgrade Notice ==

= 0.1.0 =
First beta release.
