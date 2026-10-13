# Eola Hills property landing page

Landing-page and website-management project for 9055 SE Eola Hills Rd, Amity, Oregon.

- [Project requirements and management scope](docs/project-brief.md)
- [Taabeer updater source reference](reference/taabeer/README.md)
- Original client content: `landing-page template structure.docx` (kept locally)

Website: https://www.9055eolahills.com/

Local preview: http://localhost:8085/ (Docker Desktop must be running).

Built with 310 native Elementor elements: editable navigation, hero, property sections, gallery, carousel, listing team and footer. The user's blue reference now guides the site-wide palette: deep blue, royal blue, ice blue and white. Locally hosted Manrope pairs semibold corporate headings with clear body text. A cinematic photo hero, rounded address/radar pill, connected statistics panel, elevated feature cards and asymmetric image layouts create the landing-page composition. Includes native photo-switch buttons, scroll reveals, card hovers, gallery filters/lightbox, counters and reduced-motion support. Full-resolution temporary stock photography is stored in the WordPress Media Library for replacement.

- [Install, edit and update the website](docs/installation.md)
- [Local development and verification](docs/development.md)
- [Design decisions](design-system/eola-hills/pages/landing.md)

Two packages: `eola-hills-updates` is the small installer/GitHub updater; `eola-hills` supplies the editable page, assets and interactions. The installer reuses Taabeer's encryption, file comparison, backup and dependency-installation routines. Its repository and release channel are preset. Upload only the installer ZIP on a new WordPress site, then use its install action to fetch the website and Elementor.

The client website has not been modified. Hosting access, actual property photography/video, approved branding and analytics accounts are still pending. Analytics/reporting requirements are recorded in the brief; tracking is not connected.

Code belongs in Git. Elementor content, media and settings require separate WordPress backups. Keep credentials outside tracked files.
