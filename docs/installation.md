# Install and sync

Requires WordPress 6.8+, PHP 8.0+, PHP Zip, writable theme/plugin/uploads folders and outbound HTTPS to GitHub/WordPress.org. Elementor Free is the editing dependency; no Elementor Pro is needed.

## Existing website using the old two-plugin setup

1. Download `eola-hills-updates-2.2.0.zip` (or newer) from the GitHub `client-preview` release.
2. In **Plugins → Add New → Upload Plugin**, upload it and choose **Replace current with uploaded** for Eola Hills Updates. Keep it active. This replaces the existing updater; it does not add a second custom updater.
3. Open **Tools → Eola Hills Updates → Sync from GitHub**.

That single action downloads/verifies the theme, backs up the actual legacy property-plugin files (including local differences), activates the theme, and automatically applies the client Elementor content on the redirect. It preserves the existing page ID, URL, publication status and homepage setting. After successful content sync, the old Eola Hills Property plugin is removed. The active custom project plugin is Eola Hills Updates; Elementor remains for editing.

The old plugin-file conflict message does not apply to this migration. A retained legacy ZIP is available as **Download retired property plugin backup**. There is no separate Apply client content step.

## Fresh WordPress

Upload/activate only `eola-hills-updates-2.2.0.zip` (or newer), then click **Sync from GitHub**. It installs/activates Elementor if needed, installs/activates the theme and creates the native Elementor property page. If no static homepage is configured, the page is published and set as homepage. An existing configured homepage is retained and the new page stays a draft.

The public repository is preset; no token is required. Existing private-repository token storage is preserved. Never put hosting credentials in Git.

## Updater self-update (2.2.0+)

Like the theme, the updater now updates itself from GitHub. Each release manifest lists the updater version, package and SHA-256. **Sync from GitHub** first installs a newer verified updater, keeping a downloadable backup of the previous one. The redirect then runs the new updater code, which installs the theme, and the next page load applies the Elementor content. One click covers all three steps.

Sites on 2.0.x/2.1.x need **one last manual upload** of `eola-hills-updates-2.2.0.zip` (Plugins → Add New → Upload Plugin → **Replace current with uploaded**), because those versions cannot update themselves. After that, no updater ZIP is uploaded by hand.

Since 2.0.1, previous-code backups are stored in verified 512 KB database chunks. Single-row backups of the ~17 MB theme can exceed a shared host's database packet limit.

## Future changes

Push reviewed theme changes to GitHub with a new theme version. After the release workflow succeeds, click **Sync from GitHub** in WordPress. It fetches the current release immediately without waiting for the scheduled check. Git pushes alone do not deploy to hosting.

Theme code is replaced after a private code backup. When the packaged Elementor template or media manifest changes, the delivery automatically replaces the managed page after a private page backup. This includes any manual Elementor edits on that page; Git supplies the new delivery. A code-only release leaves saved Elementor page edits intact. Between releases, every native widget/section remains editable normally.

Use Elementor's Structure panel for named sections. Gallery filters depend on their CSS classes, while images/lightboxes, fonts, colors and responsive spacing use native controls. New pages can duplicate the existing page or use saved Elementor sections.

## Recovery

- **Download previous theme backup**: upload the retained ZIP under Appearance → Themes to restore theme files.
- **Eola Hills → Restore previous page content**: restores the saved Elementor document/settings. That restoration is respected until a different client delivery is installed.
- The legacy plugin backup is retained separately after migration. Full legacy rollback also requires its matching previous page content and the previous theme; do not activate the legacy plugin alongside the Eola Hills theme.

Only the latest theme backup and latest replaced-page backup are retained. Keep complete WordPress database/media backups separately. Existing media attachments are retained so older image references remain valid. If sync reports an error, fix the reported issue and click Sync from GitHub again; failed content sync is not reported as complete.

The listing uses 3,018 sq ft. The floor plans carry the client's October 2026 corrections and no longer print the CubiCasa area totals (docs/floor-plan-revisions.md). Professional video and analytics configuration are pending.
