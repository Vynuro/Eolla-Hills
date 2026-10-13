# Eola Hills property landing page

Landing-page and website-management project for 9055 SE Eola Hills Rd, Amity, Oregon.

- [Project requirements and management scope](docs/project-brief.md)
- [Taabeer updater source reference](reference/taabeer/README.md)
- Original client content: `landing-page template structure.docx` (kept locally)

Website: https://www.9055eolahills.com/

Local preview: http://localhost:8085/ (Docker Desktop must be running).

Built with 374 native Elementor elements: editable navigation, hero, property sections, 15-photo gallery, carousel, amenities accordion, original floor plans, listing team and footer. Version 1.3.1 uses the client's Wine Country Properties Group branding: Montserrat, purple #4B5598, green #55A044, supplied combined eXp logos and actual property photography. The hero uses the exterior photograph preferred by the client. RMLS #644545554, the latest 3,018 sq ft living-area figure and both agent license numbers are included. Client assets are imported into the normal Media Library; saved page content remains editable.

- [Install, edit and update the website](docs/installation.md)
- [Local development and verification](docs/development.md)
- [Design decisions](design-system/eola-hills/pages/landing.md)

Two packages: `eola-hills-updates` is the small installer/GitHub updater; `eola-hills` supplies the editable page, assets and interactions. The installer reuses Taabeer's encryption, file comparison, backup and dependency-installation routines. Its repository and release channel are preset. Upload only the installer ZIP on a new WordPress site, then use its install action to fetch the website and Elementor.

The user has installed the earlier site at https://9055eolahills.com/. This update is prepared and verified locally for the user to install. After the GitHub code update, use Eola Hills → Apply client content and keep a page backup to update an existing Elementor page. Professional video and analytics access are still pending; tracking is not connected. See [client delivery review](docs/client-assets-review.md) for source decisions and the floor-plan area discrepancy.

Code belongs in Git. Elementor content, media and settings require separate WordPress backups. Keep credentials outside tracked files.
