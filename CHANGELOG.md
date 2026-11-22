# Changelog

All notable changes are documented here. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions are 0.x (beta) until tested on several themes.

## [0.1.0] - Unreleased (Phase 1, Part 1A)

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

### Not yet implemented
- Any front-end output (Parts 1B and 1C), sitemaps, structured data, redirects, editor sidebar.
