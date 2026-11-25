# Stage report - October 14, 2026 (historical)

> Superseded: the management package was ordered and published in November 2026. See `management.md`.

All new management-plugin work, updater changes and booklet work are LOCAL. Nothing from this work has been pushed or deployed. The user wants one combined push after the remaining client material arrives and explicitly authorizes publication.

## Available to review

- Website: http://localhost:8085/ . Native Elementor theme and the existing client-branded design remain editable.
- Analytics: http://localhost:8085/wp-admin/admin.php?page=eola-analytics . Separate Eola Analytics 1.1.0 plugin, independent activation, automatic measurement without an approval popup (visitor opt-out available), section/button/gallery/floor-plan/download activity, campaign sources, active time, returning sessions, CSV reports and dated daily client reports. No Google account is needed for this local collector.
- Care: http://localhost:8085/wp-admin/admin.php?page=eola-care . Separate Eola Care 1.1.0 plugin with daily automatic background backup and security scan (7-backup retention, dated run history and daily reports), private file/database backups, download, restore, delete, code scan, quarantine and quarantine recovery/deletion. Destructive actions require administrator checks and confirmation.
- Updater: local Eola Hills Updates 2.1.0 packages both companion plugins and preserves activation state/data. First installs stay inactive. Large previous-code backups now use verified database chunks, avoiding the previous single-packet limit.
- Booklet: `output/pdf/eola-hills-booklet-review.pdf`, twelve-page review draft using client copy, branding and property photographs. The builder now prefers the full-resolution MouveMedia originals. Original floor plans remain unchanged and explicitly marked revision pending. The booklet has not been published on the website.

## Verified locally

- Analytics browser test: automatic collection without popup or recorded consent, persistent opt-out and re-enable; gallery, floor-plan, PDF, contact and showing events; active engagement; campaign attribution; duplicate rejection; invalid-origin/event rejection; staff exclusion; responsive dashboard.
- Analytics deactivation removes the endpoint and tracker while retaining data; reactivation works. Anonymous backup operations are rejected.
- Isolated companion installs/updates preserve saved data and active/inactive states; unexpected plugin slugs and altered checksums are rejected. Large code-backup reconstruction and missing-chunk rejection pass.
- Isolated Care backup and restore recover a changed file and database value, creating a recovery snapshot first. Scan/quarantine/recover/permanent-delete tests use harmless fixtures. Backup deletion is verified.
- Actual local Care dashboard creates and downloads a private backup; unauthorized download and invalid nonce requests are rejected. Downloaded ZIP integrity and database archive entry are verified. Mobile dashboard has no horizontal overflow.
- Care automation: isolated test completes a real automatic backup and scan without a browser; disabled schedule, restore/maintenance and overlap guards, same-day deduplication and seven-backup retention (manual backups untouched) pass. Local scheduler container runs the main local site in the background.
- Daily reports: dated analytics/care CSV and printable HTML downloads, invalid-date and forged-nonce rejection, DST/day-boundary aggregation and mobile layouts pass.
- PHP syntax checks pass for the analytics, care and updater implementations. Release packages are built locally and validate Elementor IDs and client-media hashes.
- All twelve final booklet pages were rendered and visually reviewed. Listing area, RMLS number, agent names/licenses, review labels and distinct embedded regular/semibold fonts are verified. The optimized PDF is approximately 16.5 MiB. QA analytics events tagged `eola_review` have been removed from the local dashboard after testing.

## Pending and why

| Work | Required input or next step |
| --- | --- |
| Correct labels, covered patio and barn doors | Three-page blue-ink scan or equally precise confirmed instructions; no marked-up scan was found in any client folder/archive. The supplied clean originals do not establish the requested changes. |
| Approve final MLS floor-plan export | Client identification of the intended MLS source and approval of corrections; resolve the printed 3,764 sq ft versus confirmed 3,018 listing area. |
| Final booklet for printing | Revised plans, final trim size/bleed/printer specifications and approval. An existing booklet PDF is optional for a new design, needed only to revise/preserve an existing one. |
| Property video tracking | No property video/player supplied. HTML5 hooks exist; any external video player needs its own adapter and verification. |
| Print QR attribution | Confirm campaign/destination before a QR code is put into final print artwork. |
| Google search queries/indexing reports | Search Console access is needed for Google's search data; the custom collector cannot reconstruct it. |
| Production backup/recovery readiness | Hosting access and an isolated staging restore to verify permissions, private storage and execution limits. Local tests do not prove hosting behavior. |
| Analytics and Care (backup/scan) release | Built and tested locally; held until the client order for these services arrives. Not pushed. |
| Site-wide IP rate limiting / block report | Deferred management-order scope, see `future-management-scope.md`. Not implemented. |
| Live cron | When hosting is available, configure server cron for WordPress every five minutes so daily tasks run on time. |
| Git/live rollout | Intentionally held at the user's instruction; combine changes after remaining material arrives and the user authorizes push. |

## Limits to retain in handoff

Analytics reports measured browsers (blockers/opt-outs reduce coverage), not all people, confirmed leads or a heatmap. Retention is 90 days. Care is a scheduled/manual backup/recovery and suspicious-file review tool, not a full antivirus, WAF or off-site backup service. Restore retains wp-config.php and the running Care plugin and does not remove files added after the snapshot. See `analytics.md`, `care.md` and `booklet-review.md` for operating details.
