# Open Cookie Database — vendored snapshot

This directory bundles a copy of the **Open Cookie Database**
(`open-cookie-database.csv`), used by Biscotto's cookie scanner to fill in
service name, category, retention period and privacy-policy link for cookies
the built-in classifier doesn't recognize by itself.

- Source: https://github.com/jkwakman/Open-Cookie-Database
- License: Apache License 2.0 (https://www.apache.org/licenses/LICENSE-2.0)
- Snapshot date: 2026-07-04
- File: `open-cookie-database.csv` (unmodified from upstream `master`)

This is a static, manually-updated snapshot. Biscotto does not
automatically download or update this file. The plugin's "Check for
database updates" button (Settings → Biscotto → Scan) makes an on-demand,
admin-triggered call to the public GitHub API to check whether a newer
snapshot exists upstream — see `class-biscotto-cookie-database.php` and
`class-biscotto-scanner.php`.
