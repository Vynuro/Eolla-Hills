# Installation and editing

## One-plugin installation

Requires WordPress 6.8+, PHP 8.0+ with Zip and Sodium or OpenSSL, writable plugin/uploads directories, and outbound HTTPS to GitHub and WordPress.org. Elementor Free is installed automatically if missing; Elementor Pro is not required. Confirm Elementor's current requirements when choosing hosting.

1. Install WordPress on the client's hosting.
2. Upload `eola-hills-updates-1.0.0.zip` in Plugins → Add New → Upload Plugin and activate it.
3. Open Tools → Eola Hills Updates. The `malikazhar545/5eolahills` repository and `client-preview` release channel are already configured.
4. For a private repository, save a fine-grained GitHub token scoped to this repository with Contents: Read-only. Public releases need no token. A token can instead be defined as `EOLA_HILLS_GITHUB_TOKEN` in `wp-config.php`.
5. Click **Check GitHub**, then **Install website and Elementor**. The installer downloads the property plugin, verifies its checksum, installs/activates Elementor and creates a draft on the following admin request.
6. Open Eola Hills → Edit with Elementor. Review the supplied real photos, branding, contacts and listing facts. Add the professional video when supplied.
7. Publish the reviewed page. For a homepage, choose it in Settings → Reading. The installer deliberately leaves an existing homepage/theme unchanged.

The repository must have a successful release workflow before step 5 can fetch packages. The ZIPs can also be built locally with `python tools/package_plugins.py`. If GitHub cannot be reached, manually install Elementor Free, upload/activate the property ZIP, and choose Eola Hills → Create draft landing page.

## Elementor editing

Use the Structure panel's named sections. Header, footer and content are native containers and widgets. Click text, images or buttons directly; use Style for colours/fonts and Advanced for responsive spacing. Image carousel photo height is under Style → Image → Photo height, with desktop/tablet/mobile settings. The native carousel controls manage slides, arrows, dots and animation speed. Every photo can be replaced through the normal Media Library.

Gallery filters rely on `eola-filter-*` button classes and `eola-category-*` tile classes in Advanced → CSS Classes. Keep those classes when duplicating gallery entries. Native image links use Elementor's lightbox. Tour buttons currently open Linda's email composer; edit the links if a booking service is supplied.

Colours, logos and Montserrat come from the supplied client brand guide. Motion is a plugin enhancement; page content and visual settings remain stored in Elementor. New pages can use duplicated native sections or saved Elementor templates. Reviewed client-media provenance and checksums are recorded in `assets/media/manifest.json`; the Montserrat licence is bundled.

## Future code updates

The developer changes PHP/CSS/JavaScript in Git, increments the property plugin version in its header and constant, and pushes to `main`. GitHub Actions builds the package and SHA-256 manifest on `client-preview`. From WordPress, click **Check GitHub** → **Back up and install release**. Checking is automatic twice daily; installation is an explicit admin action.

Updates replace plugin code/assets. They do not overwrite saved Elementor content, colours, layout, media or page settings. A changed seed template affects newly created pages, not existing pages; apply content/design changes to existing pages in Elementor or through an explicit migration. Direct edits to plugin files that would be overwritten block the update until reconciled in Git.

Before replacing code, the installer retains the previous plugin ZIP privately in the database. Download it from Tools → Eola Hills Updates and restore it through Plugins → Upload Plugin if needed. This is a code backup only; keep separate database/media backups. Only the most recent code backup is retained. The installer plugin itself is updated manually using its own release ZIP.

Changing WordPress authentication salts invalidates a saved encrypted token; save the token again if that happens. No token, hosting login or local `.env` belongs in Git.


## Applying the client package to the existing live page

1. Open **Tools → Eola Hills Updates → Check GitHub**, then install release **1.3.0** or later. This changes plugin code and keeps the existing page intact.
2. Open **Eola Hills** in the WordPress admin menu.
3. Click **Apply client content and keep a page backup**. The action imports the actual photos/documents and replaces the existing property page's content and layout with the client package. The page URL, published status and homepage remain unchanged. Any manual Elementor content edits are replaced by this explicit action.
4. Check the front end and clear any hosting/page cache. Continue editing normally in Elementor.

A private backup of the previous page data/settings is saved before replacement. **Restore previous page content** reverses the content change; existing media is retained so old image references continue working. Only one previous-page backup is retained. Applying the same content version twice is blocked to protect later Elementor edits. This is separate from the updater's plugin-code backup and does not replace complete WordPress backups.

The listing shows 3,018 sq ft from the latest supplied documents. Original floor plans were included unchanged at the user's explicit request despite their differing printed totals; the page discloses that difference.
