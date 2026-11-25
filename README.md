# Eola Hills WordPress website

Website for 9055 SE Eola Hills Rd, Amity, Oregon. Local preview: http://localhost:8085/.

**Website management (November 2026):** Eola Care (daily backups, security scans, visitor rate and hacking-attempt blocking, emailed report every 3 days), Eola Analytics (general analytics) and Limit Login Attempts Reloaded are installed by the same GitHub Sync. See [docs/management.md](docs/management.md).

- [Analytics scope and reporting](docs/analytics.md)
- [Private backups, restore and scan limits](docs/care.md)
- [12-page booklet review proof and pending inputs](docs/booklet-review.md)
- [Current stages, checks and outstanding work](docs/stage-report.md)

Version 2.0.0 uses the requested Taabeer architecture: **one Eola Hills theme and one custom GitHub updater plugin**, plus Elementor Free for editing. Install the updater once, then click **Tools → Eola Hills Updates → Sync from GitHub**. Since 2.2.0 the updater also updates itself from the same verified release, then installs the theme and automatically syncs changed client layouts/media with backups. No separate content-apply action.

The theme contains 374 native Elementor elements, client photos and brokerage logos, Montserrat and supplied branding, 3,018 sq ft listing details, RMLS #644545554, both agent license numbers, client-corrected floor plans (see docs/floor-plan-revisions.md) and the amenities PDF. Header, footer, sections, text, photos, colors and responsive settings remain editable.

Existing 1.x sites migrate automatically: the updater backs up actual old property-plugin files, activates the theme, backs up/replaces the old Elementor delivery, preserves the page URL/status/homepage, then removes the retired property plugin. Existing GitHub authentication is retained.

- [Installation, migration and recovery](docs/installation.md)
- [Local development and verification](docs/development.md)
- [Full client-folder review](docs/client-assets-review.md)
- [Project and management scope](docs/project-brief.md)

Professional video and revised floor-plan source/markups remain pending. Custom analytics does not require a Google account; Google search performance still needs Search Console. No live deployment has been performed.
