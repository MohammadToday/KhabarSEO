# Security policy

## Supported versions

Only the latest 0.x release is supported while KhabarSEO is in beta.

## Reporting a vulnerability

Please do **not** open a public issue. Report vulnerabilities privately through GitHub private vulnerability reporting:
open the [KhabarSEO repository](https://github.com/MohammadToday/KhabarSEO), go to the **Security** tab and choose **Report a vulnerability**.

Include the plugin version, WordPress and PHP versions, and steps to reproduce. You will get an acknowledgement within a few days.

## Scope notes

* All settings screens and handlers require the `manage_options` capability (filterable with `khs_capability`) and a nonce.
  Per-post fields require `edit_post` for that post, per-term fields `edit_term` for that term.
* Imported settings files go through the same schema sanitizer as the settings forms; unknown keys are discarded.
* The only HTTP request the plugin makes is an admin-triggered loopback request to the site's own host (Status, Scan now).
