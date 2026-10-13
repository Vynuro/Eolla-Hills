# Local development

Preview: http://localhost:8085/. Credentials are in untracked `.env`. Docker Desktop must be running.

The source theme and the single updater are bind-mounted into the main local site. Never install ZIPs over those mounts. Use the isolated `eola-updatecheck` project on port 8086 for migration/update tests and `eola-fresh` on 8087 for clean installation.

## Build and release

```
python tools/build_template.py
python tools/package_plugins.py
```

The build validates 374 native Elementor elements and all 27 client-media hashes. Outputs are `eola-hills-theme-<version>.zip`, `eola-hills-updates-<version>.zip` and `eola-hills-theme-update.json`. The 2.x updater accepts only the theme manifest/package. Legacy 1.x assets can remain on GitHub for recovery; they are not used by 2.x.

Increment the theme version in `style.css` and `functions.php` when shipping theme changes. The delivery fingerprint is computed from the template and media manifest. It changes automatically when packaged page content/assets change. Such releases back up and replace the managed Elementor document; code-only releases preserve later native edits. The updater finishes delivery automatically on the redirect after WordPress loads the new theme.

GitHub Actions lints PHP, checks template generation is deterministic, packages assets and publishes the release. The live site changes only when the user clicks Sync from GitHub.

## Local refresh

```
docker compose up -d wordpress
docker compose run --rm cli eval-file /work/tools/sync-local-theme.php
```

This intentionally applies a changed delivery to the local page. `refresh-local.php` is a developer-only unconditional reseed; it is never packaged or used on production.

## Verification

Browser tests: `node tools/browser-check.cjs`, `node tools/interaction-check.cjs`, `node tools/client-assets-check.cjs`, `node tools/editor-check.cjs`. They cover five viewports, real media, gallery/hero/carousel behavior, original plans/PDF, client details and an actual native heading edit/publish/frontend verification/restore. The editor allows four minutes for initial rendering on the memory-constrained local machine. Run screenshots after editor saves finish because Elementor regenerates CSS.

Isolated migration: install the new updater ZIP into the port-8086 instance, then run `verify-theme-migration.php` and, on the next CLI request, `verify-theme-delivery.php`. They exercise changed legacy files, backup failure, permissions, checksums/path traversal, theme installation, automatic content replacement, exact backups, native edit preservation, restoration and retirement of the second custom plugin.

Future update: `make_update_fixture.py` generates a private next-version theme fixture with changed content. Run `verify-theme-upgrade.php` and then `verify-theme-upgrade-delivery.php` on the isolated instance. These verify that direct theme-file changes are backed up and replaced and new Git content is applied automatically.

`verify-github.php` fetches and installs the real published theme release on the isolated instance; `verify-fresh-delivery.php` verifies a new site after the next request. No fixture or test credential is published.

The full client-folder audit is in `client-assets-review.md`. Floor-plan PNGs/PDF are original bytes. Property photographs retain 2200px maximum dimensions and WebP quality 85; logos are lossless. `prepare_responsive_assets.py` prebuilds responsive photo sizes and records their checksums. The importer copies those sizes and writes native Media Library metadata, so hosting does not spend minutes resizing the delivery. Run that script after `prepare_client_assets.py` when rebuilding original assets. Raw deliveries remain ignored. Theme photos and fonts are committed so CI needs no client folders or external asset downloads.

Taabeer reuse retains its encryption, theme manifest, baseline and ZIP-backup routines in the updater trait, and its Elementor dependency/kit setup routines. Names, repository and options are scoped to Eola Hills. The requested 2.x sync intentionally treats Git as authoritative for theme files, with verified backups instead of blocking on legacy file differences.
