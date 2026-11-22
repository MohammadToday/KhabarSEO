# Security policy

## Supported versions

Only the latest 0.x release is supported while the plugin is in beta.

## Reporting a vulnerability

Please do **not** open a public issue. Use GitHub's private vulnerability reporting (*Security → Report a vulnerability*) on this repository.
Include the plugin version, WordPress and PHP versions, and steps to reproduce. You will get an acknowledgement within a few days.

## Scope notes

* All admin actions require the `manage_options` capability (filterable with `khs_capability`) and a nonce.
* Imported settings files go through the same sanitizer as the settings forms; unknown keys are discarded.
* The only HTTP request the plugin makes is an admin-triggered loopback request to the site's own host.
