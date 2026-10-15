# Eola Hills WordPress website

Website for 9055 SE Eola Hills Rd, Amity, Oregon. Local preview: http://localhost:8085/.

Version 2.0.0 uses the requested Taabeer architecture: **one Eola Hills theme and one custom GitHub updater plugin**, plus Elementor Free for editing. Install the updater once, then click **Tools → Eola Hills Updates → Sync from GitHub**. It installs the theme and automatically syncs changed client layouts/media with backups. No separate content-apply action.

The theme contains 374 native Elementor elements, client photos and brokerage logos, Montserrat and supplied branding, 3,018 sq ft listing details, RMLS #644545554, both agent license numbers, client-corrected floor plans (see docs/floor-plan-revisions.md) and the amenities PDF. Header, footer, sections, text, photos, colors and responsive settings remain editable.

Existing 1.x sites migrate automatically: the updater backs up actual old property-plugin files, activates the theme, backs up/replaces the old Elementor delivery, preserves the page URL/status/homepage, then removes the retired property plugin. Existing GitHub authentication is retained.

- [Installation, migration and recovery](docs/installation.md)
- [Local development and verification](docs/development.md)
- [Full client-folder review](docs/client-assets-review.md)
- [Project and management scope](docs/project-brief.md)

Professional video and analytics access remain pending. This work is local; the user installs the release on the live website.
