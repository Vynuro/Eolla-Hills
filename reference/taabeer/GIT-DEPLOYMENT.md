# TAABEER GitHub Deployment

The WordPress site receives theme code through **TAABEER Deployment Manager**. It never runs Git commands on the web server.

## First connection

1. Install taabeer-deployment-manager-1.0.1.zip in WordPress.
2. Activate it and open **TAABEER > GitHub updates** (or **Tools > TAABEER Updates** before theme 1.1.0).
3. Paste https://github.com/malikazhar545/Taabeer-.git.
4. If the repository is private, create a fine-grained GitHub personal access token restricted to this repository with **Contents: Read-only**, then paste it into GitHub authentication.
5. Save, select **Check GitHub**, then select **Install and activate**.

The first deployment installs the theme, activates it and runs its idempotent demo importer. A later deployment checks for conflicting live theme-file edits and creates a theme backup before replacing code. WordPress database content is preserved.

## Publish a code update

1. Make and test changes in this repository.
2. Increase the version in wordpress/wp-content/themes/taabeer/style.css and TAABEER_VERSION in functions.php.
3. Commit and push to main.
4. Wait for the **Publish TAABEER client preview** GitHub Action.
5. In WordPress, open **TAABEER > GitHub updates** (or **Tools > TAABEER Updates** before theme 1.1.0), select **Check GitHub**, review the version, then select **Back up and install**.

Each GitHub Action run packages the theme, verifies its structure, generates a SHA-256 manifest and replaces the assets on the fixed client-preview release.

## Removing access

Use **Disconnect GitHub** to remove the saved repository and encrypted token. Deactivating or deleting the plugin stops all update checks. The currently installed website remains unchanged.
