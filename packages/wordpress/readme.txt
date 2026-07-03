=== ConsentKit ===
Contributors: renatosaka
Tags: cookie, consent, gdpr, cookie banner, consent mode
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GDPR/ePrivacy cookie consent compliant with the Italian DPA (Garante) guidelines: Google Consent Mode v2, GTM and LinkedIn. No page or CPT limits.

== Description ==

ConsentKit is an open-source cookie consent manager with no artificial limits (no caps on pageviews, pages or Custom Post Types).

Features:

* Compliant consent banner: close (X) button, equal Accept/Reject buttons, link to the privacy notice, granular preferences.
* Prior blocking of scripts (`type="text/plain"` + `data-ck-category`) until consent is given.
* Google Consent Mode v2 (default denied before GTM, update on consent).
* Google Tag Manager via dataLayer.
* LinkedIn Insight Tag loaded only with marketing consent.
* Compliant banner re-prompt (minimum 6 months) and re-consent when the cookie policy changes.
* Optional pseudonymized consent log for GDPR audits.
* Runtime cookie scanner: loads your pages in a hidden iframe (admin only) and detects the cookies and third-party domains actually loaded, then suggests registry entries to review and save.

The core is a dependency-free JavaScript engine, reusable on non-WordPress sites too.

Cookies are managed through a pre-filled registry of the most common services, editable by hand from the admin and extendable with the built-in scanner.

Roadmap (in progress):

* Automatic recognition and classification of the detected cookies through a public database (service, purpose, duration, category).
* Automatic blocking of iframes and embeds (Google Maps, YouTube) and Google Fonts with a "click to load" placeholder.

== Installation ==

1. Upload the `consentkit` folder to `/wp-content/plugins/`.
2. Activate the plugin from the Plugins menu.
3. Go to Settings &rarr; ConsentKit and configure texts, cookies and integrations.

== Frequently Asked Questions ==

= Does it work with Custom Post Types? =
Yes, with no extra configuration and no limits.

= Is it compliant with the Italian Data Protection Authority (Garante)? =
The plugin implements the technical requirements of the 10 June 2021 guidelines. Overall compliance also depends on a correct privacy notice and on the proper classification of each site's cookies.

= Does it send data to external services? =
No. ConsentKit does not communicate with any third-party server. It loads the Google (Consent Mode/GTM) and LinkedIn scripts only after consent and only if you configure them. The optional consent log stays in your site's database and is pseudonymized.

== Screenshots ==

1. Compliant consent banner (bottom bar).
2. Granular per-category preferences panel.
3. Settings &rarr; General: texts, color, re-prompt.
4. Settings &rarr; Cookies: cookie registry.
5. Settings &rarr; Integrations: Consent Mode v2, GTM, LinkedIn.

== Upgrade Notice ==

= 1.2.0 =
New banner position "Bottom-right box": a compact card on desktop, full-width bar on mobile.

= 1.1.1 =
Scan tab: load the URLs to scan from the site sitemap, with clearer guidance.

= 1.1.0 =
Adds a runtime cookie scanner to detect cookies and third-party services loaded on your pages.

= 1.0.0 =
First public release.

== Changelog ==

= 1.2.1 =
* Improved: larger, more legible banner text on smartphones (body 14&rarr;17px, title 16&rarr;20px, links 13&rarr;15px, bigger tap targets on buttons). Desktop appearance unchanged.

= 1.2.0 =
* New: banner position "Bottom-right box". On desktop the banner shows as a compact card anchored to the bottom-right corner instead of a full-width bar; on mobile it falls back to a full-width bottom bar for easy tapping. Selectable under Settings &rarr; General &rarr; Banner position.

= 1.1.1 =
* Scan tab: "Load from sitemap" button populates the URL list from the site sitemap (wp-sitemap.xml / sitemap_index.xml), with REST fallback. Only same-origin URLs are added.
* Clearer help text: the scanner checks only the listed URLs (not the whole site); the homepage already covers site-wide scripts.

= 1.1.0 =
* New: runtime cookie scanner (Scan tab). Loads target pages in a hidden, admin-only iframe with consent forced to "accepted", reads cookies, storage and third-party resource domains, and suggests registry entries to review and import.
* Internal classifier mapping common domains and cookie names to service and category. No external calls and no third-party data bundled.

= 1.0.0 =
* First release: banner, granular preferences, Consent Mode v2, GTM, LinkedIn, prior blocking, optional log.
