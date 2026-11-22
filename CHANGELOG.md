# Changelog

All notable changes are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions are 0.x (beta) until tested on several themes.

## [0.2.0] - 2026-11-22 (Phase 1, Part 1B)

### Added
- Document titles from templates with the variables `%title%`, `%sitename%`, `%tagline%`, `%category%`, `%author%`, `%date%`, `%sep%`, `%page%` and `%excerpt%`.
  Templates per content type, per taxonomy, and for the home page, author, date and content type archives, search results and 404 pages.
  Posts default to `%title% %sep% %sitename%`. Empty variables never leave doubled or dangling separators.
- Meta descriptions from templates: written excerpt, optionally the start of the article (cut at a word boundary, ZWNJ-safe), term descriptions and author biographies. Passages hidden from snippets are never used.
- Robots meta merged into the core `wp_robots` filter, so there is exactly one robots tag. Default `index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1`; conflicts resolve to the more restrictive rule.
- Archive rules: tags (none / with at least N posts / all), categories and other taxonomies, authors (only with published posts), date archives, internal search (always `noindex, follow`), 404, attachment pages, page 2 and later.
- Canonical links on every indexable context (core's singular-only `rel_canonical` is replaced): page N of lists and multi-page articles points to itself, tracking parameters are removed (configurable list), URLs are normalized so Persian slugs are encoded identically. No canonical on noindex pages unless enabled.
- Attachment pages of published posts redirect (301) to the post; other attachments keep the WordPress behavior.
- Per-post overrides in a server-rendered metabox (Block and Classic editors): SEO title, description, canonical, noindex, nofollow, nosnippet, max-snippet. Per-term overrides on the term edit screen.
- "Hidden from search snippets" block and `[khs_nosnippet]` shortcode that output `data-nosnippet` (div, or span with `inline="1"`).
- Status: search engine visibility check, title-tag support check, meta descriptions in the head scan.
- Opt-in compatibility mode for themes without title-tag support (`wp_title`).
- `bin/merge-po.php`, `bin/build-repo-zip`, `bin/check-generality` (CI fails when runtime code names a theme or plugin).

### Changed
- Settings schema version 2. The bullet separator became a middle dot (`·`); stored and imported values are migrated.
- The timezone check is data-driven (`data/timezones.php`) instead of naming a zone in code.
- Repository identity: `MohammadToday/KhabarSEO`, author Mohammad Ahmadi Akbari, copyright notice in README and readme.txt.

### Not yet implemented
- Open Graph and X cards, head cleanup, URL permanence and date guards (Part 1C); sitemaps, structured data, redirects and the editor sidebar (later phases).

## [0.1.0] - 2026-11-21 (Phase 1, Part 1A)

### Added
- Plugin scaffold: PSR-4 autoloader (no runtime Composer), requirements gate (PHP 8.1+, WordPress 6.5+, mbstring), activation (single site and network), uninstall honoring "keep data".
- Central settings schema with versioned migrations, sanitizer, export/import (own JSON), reset. Autoloaded option capped at 8 KB; cold data in a non-autoloaded option.
- Tabbed settings panel (Setup, Titles & Meta, Indexing & Archives, Social, Advanced, Tools, Status) with Recommended badges and plain-language guides.
- Five-step setup wizard ending in the "News site, recommended" preset.
- Safe takeover: output is off until the wizard is finished and while another SEO plugin is active. Kill switch `KHS_DISABLE`; developer override `KHS_FORCE_OUTPUT`.
- Conflict scanner (plugin detection plus admin-only loopback parse of the rendered head).
- Status page with copyable diagnostics, PHP end-of-life warning, timezone and date-conversion checks.
- Helpers: `Url::normalize()`, `Text::truncate()`, `Time::iso_from_gmt()`.
- Complete `fa_IR` translation, POT extractor and `.po` to `.mo` compiler (no gettext needed).
- Repository: GPL-2.0-or-later license, CI workflow (lint on PHP 8.1 to 8.5, smoke tests, PHPCS, i18n), issue templates.
