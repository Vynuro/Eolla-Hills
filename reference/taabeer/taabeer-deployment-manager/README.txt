=== TAABEER Deployment Manager ===
Requires at least: 6.4
Requires PHP: 8.0
Version: 1.0.1
License: GPL-2.0-or-later

Installs and updates the TAABEER theme from a verified GitHub release without theme ZIP uploads.

== Connect ==

1. Install and activate this plugin.
2. Open Tools > TAABEER Updates.
3. Paste the full GitHub repository link.
4. For a private repository, enter a fine-grained GitHub token with read-only Contents access.
5. Save the connection and select Check GitHub.
6. Select Install and activate, or Back up and install for later versions.

The token is encrypted with this WordPress site's authentication salt and is never displayed after saving. It can alternatively be defined in wp-config.php with TAABEER_UPDATER_GITHUB_TOKEN.

== Safety ==

The plugin validates the release manifest, SHA-256 checksum, archive paths, required theme files and package version. It also detects direct changes to live theme files and blocks an update if the incoming package would overwrite them. Before an existing theme is replaced, it creates a protected backup under wp-content/taabeer-update-backups.

On the first theme installation it activates TAABEER and runs the idempotent demo setup once. Later code deployments preserve Elementor layouts, pages, media, products, orders and settings stored in the WordPress database.

Deactivating or deleting this plugin stops repository update checks. The installed theme and site content remain available. Deleting the plugin also removes its saved repository connection and encrypted token.

== Publishing updates ==

Push reviewed code to main after increasing the theme version in style.css and functions.php. GitHub Actions publishes the verified package and manifest to the client-preview release channel.
