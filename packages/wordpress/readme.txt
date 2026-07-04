=== ConsentKit ===
Contributors: renatosaka
Tags: cookie, consent, gdpr, cookie banner, consent mode
Requires at least: 5.9
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.4.0
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
* Cookie database enrichment: fills in missing service, category, retention period and privacy-policy link using a bundled copy of Open Cookie Database (Apache-2.0), with an optional manual check for dataset updates.
* One-click "copy code" box for building your cookie policy page from the plugin's shortcode.

The core is a dependency-free JavaScript engine, reusable on non-WordPress sites too.

Cookies are managed through a pre-filled registry of the most common services, editable by hand from the admin and extendable with the built-in scanner.

Roadmap (in progress):

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
Only in one specific, opt-in case: the "Check for database updates" button (Settings → ConsentKit → Scan) contacts the public GitHub API only when you click it, to check whether the bundled Open Cookie Database snapshot is outdated. No personal or site data is sent — only a request for the latest commit date of a public file, cached for 24 hours. Everything else stays local: ConsentKit does not otherwise communicate with any third-party server. It loads the Google (Consent Mode/GTM) and LinkedIn scripts only after consent and only if you configure them. The optional consent log stays in your site's database and is pseudonymized.

== Screenshots ==

1. Compliant consent banner (bottom bar).
2. Granular per-category preferences panel.
3. Settings &rarr; General: texts, color, re-prompt.
4. Settings &rarr; Cookies: cookie registry.
5. Settings &rarr; Integrations: Consent Mode v2, GTM, LinkedIn.

== Upgrade Notice ==

= 1.4.0 =
Scan tab gains database-enrichment and update-check buttons; Cookies tab gains a one-click "copy shortcode" box for your cookie policy page.

= 1.2.0 =
New banner position "Bottom-right box": a compact card on desktop, full-width bar on mobile.

= 1.1.1 =
Scan tab: load the URLs to scan from the site sitemap, with clearer guidance.

= 1.1.0 =
Adds a runtime cookie scanner to detect cookies and third-party services loaded on your pages.

= 1.0.0 =
First public release.

== Changelog ==

= 1.4.0 =
* New: "Enrich from database" button (Scan tab) fills in missing service, category, retention period and privacy-policy link for scan suggestions using a bundled copy of Open Cookie Database (Apache-2.0, no external calls). Never overwrites a field you already set.
* New: "Check for database updates" button (Scan tab) checks, only when you click it, whether a newer snapshot of Open Cookie Database is available upstream on GitHub. No automatic checks, no site data sent.
* New: "Copy code" box (Cookies tab) with the `[consentkit_cookie_policy]` shortcode ready to paste into your cookie policy page.

= 1.3.3 =
* Fixed: mobile action buttons now share equal width regardless of label length; "Manage preferences" moved to its own centered row below Accept/Reject. Landscape phones: reduced typography/padding so the banner fits in about half the screen instead of overflowing.

= 1.3.2 =
* Fixed: primary/link buttons now resist being restyled by the host theme (explicit color/background/border rules) so the accent color and auto-contrast text always render correctly regardless of theme CSS.

= 1.3.1 =
* Fixed: mobile banner text and buttons now actually scale up on the "Bottom bar" position (fixed a CSS specificity issue where base rules were overriding the compact-mode typography).

= 1.3.0 =
* New: banner position "Bottom-left box", mirroring "Bottom-right box".
* Improved: responsive banner behaviour is now driven by width/orientation/height instead of special cases — compact full-width bar on phones (any orientation) and portrait tablets, unchanged desktop appearance otherwise.
* New: automatic text contrast (WCAG relative luminance) when background/accent colors are customized and the text-color fields are left empty.

= 1.2.4 =
* Fixed: on the "Bottom bar" position the enlarged title and body text were overridden by base rules (equal CSS specificity); the banner box grew but the fonts stayed small. Typography now uses a higher-specificity selector and renders large as intended.

= 1.2.3 =
* Improved: the "Bottom bar" banner position is now a full-width band about half the screen tall, with large readable text and buttons that scale with the viewport. More prominent, Complianz-style presence. Desktop keeps sensible size caps.

= 1.2.2 =
* Improved: banner text on smartphones enlarged further for readability (body 19px, title 23px, links 16px). Desktop appearance unchanged.

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
