# Eola Analytics - local implementation

Independent plugin with its own **Eola Analytics** menu in WordPress. No Google account or external tracking script is required. Activate/deactivate it separately from the theme, Elementor and Git updater. Collection can also be paused while keeping the dashboard available.

## Measured activity

- Page views on published public posts/pages; measured browsers and sessions.
- Source, medium and campaign from UTM tags, otherwise external referrer hostname or direct.
- Approximate active time: visible tab, interaction within the last 30 seconds; no idle/background time.
- Section views and active seconds, gallery opens/filters, hero/carousel changes, floor-plan opens, PDF links, amenities, navigation, phone/email and showing-button clicks.
- HTML5 video starts/completions. No professional video is supplied yet; YouTube/Vimeo embeds require their player-specific adapter and verification after delivery.
- Returning sessions from a previously observed browser within retained history (browser/device level, not a named person).
- 7/30/90-day reports, daily sessions, page/device breakdowns, and CSV export for weekly/monthly management reporting.
- Dated daily report: choose a calendar day (site timezone) and download a CSV or a printable client HTML report (Print / Save as PDF).

Reports show real collected activity only. No sample data is installed. A contact click is not a confirmed enquiry, a booking or proof of a buyer. A PDF click does not prove the download completed. Section time is an attention estimate, not a heatmap or exact reading duration.

## Collection and safeguards

Measurement starts automatically when a public page loads; there is no approval popup and automatic collection is not recorded as consent. A preferences button lets a visitor opt out (and later re-enable); Global Privacy Control and Do Not Track are respected. Browser IDs expire after 90 days; session IDs use tab storage with a 30-minute inactivity window. Ad blockers, disabled JavaScript, browser storage restrictions and opt-outs reduce coverage, so totals are measured browsers, not every visitor. Confirm the privacy policy wording and applicable notice requirements with the client before live activation.

The endpoint accepts a bounded allowlist of events for public pages, checks the origin, filters common bot user agents, limits request rates, and deduplicates retried event IDs. Public measurement endpoints cannot prove human traffic. Event records store keyed browser/session hashes, not raw IPs, full referrer URLs, email addresses, form entries or user agents. A short-lived keyed IP digest is used for rate limiting. Logged-in WordPress users and Elementor previews are excluded.

Raw events expire after 90 days through WP-Cron. The dashboard and CSV require administrator capability; settings and exports require nonces. Hosting access logs and backups have their own retention. Add the approved privacy policy in WordPress Settings > Privacy so the choice panel can link to it.

## Campaign convention

Use campaign-specific links before creating or printing QR codes. Example for the booklet:

`https://9055eolahills.com/?utm_source=booklet&utm_medium=qr&utm_campaign=eola_hills_listing`

Use short lowercase letters, numbers, underscores or hyphens in campaign names; never put personal information in tags. An existing untagged QR code cannot retrospectively be identified as QR traffic. Client campaign names and distribution channels remain to be supplied.

## Git behavior

The release manifest includes separate analytics/care packages with SHA-256 checksums. **Sync from GitHub** installs newer companion versions; a first install starts active, and later updates preserve the owner's on/off choice and saved records.

Since 1.2 the dashboard shows general analytics; detailed reports are locked (see `management.md`). Purge a hosting/page cache after activation/deactivation so cached pages reflect the new frontend state.

Search Console remains necessary for Google search queries, indexing and search-performance reports. Neither this plugin nor GA4 can reconstruct historical data that was never collected. Native event/endpoint architecture follows the [WordPress REST API](https://developer.wordpress.org/rest-api/extending-the-rest-api/routes-and-endpoints/) and [plugin database guidance](https://developer.wordpress.org/plugins/creating-tables-with-plugins/).
