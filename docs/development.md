# Local development

Preview: http://localhost:8085/ — editor: http://localhost:8085/wp-admin/post.php?post=5&action=elementor (current local database).

Local admin credentials are in the untracked `.env`; they are not client credentials. Docker Desktop must be running. `docker compose up -d wordpress` restarts the existing local site. Source plugins are bind-mounted; their edits apply locally. Do not install releases over these bind mounts: use the isolated update-test project on port 8086 instead.

The current local database already has WordPress, Elementor and the property page. For a clean environment, install WordPress with WP-CLI, create the `eola_admin` user, install/activate Elementor and both project plugins, then run `docker compose run --rm cli eval-file /work/tools/setup-local.php`. That development script publishes the local page, sets it as homepage and disables search indexing. Neither distributed plugin does this automatically.

## Build

```
python tools/build_template.py
python tools/package_plugins.py
```

The first command generates native Elementor JSON; the second creates deterministic ZIPs and a checksum manifest in ignored `artifacts/`. Bundled WebP photos and font files are committed, so packaging needs no third-party asset downloads. Optional `fetch_demo_photos.py` and `fetch_fonts.py` record their public sources.

After deliberately changing the seed template during development only:

```
docker compose run --rm cli eval-file /work/tools/refresh-local.php
```

This **replaces the local preview's Elementor document**, so do not run it after making local client edits you want to retain. Both CLI setup/refresh scripts reject other site URLs and are excluded from plugin ZIPs.

## Verification

```
npm ci
npm run test:browser
npm run test:interactions
npm run test:editor
```

Browser checks use installed Chrome (or `CHROME_PATH`) and the running port-8085 site. They cover four viewport sizes, image loading, overflow, links, noindex, gallery filtering/lightbox, carousel, reduced motion and a real Elementor edit/save/frontend verification/restore. Screenshots go in `artifacts/screenshots/`.

The isolated WordPress instance uses `docker compose -p eola-updatecheck -f compose.yaml -f compose.update-test.yaml` and port 8086, without source-plugin mounts. `verify-bootstrap.php` exercises the one-installer dependency/package install. `make_update_fixture.py` creates a local next-version ZIP; `verify-wordpress.php` exercises an actual Plugin_Upgrader replacement while verifying that edited Elementor data survives byte-for-byte. Its GitHub package HTTP response is replaced with local fixture bytes; this test alone does not verify GitHub authentication or availability. Also tests package checksum, project/version mismatch, path traversal, permissions, file conflicts, backup integrity and lock release.

## Taabeer reuse

`tools/reuse_taabeer.py` extracts six deployment methods and the dependency/kit setup routines from the original `D:\Taabeer` source. Runtime copies live in `eola-hills-updates/inc/trait-taabeer-deployment.php` and `inc/taabeer-foundation.php`. Package targeting is adapted from theme to plugin; dependency list narrowed to Elementor. The original updater reference is preserved under `reference/taabeer/`.

## Deployment boundary

GitHub releases distribute code; publishing a release does not deploy or publish the client's website. No hosting login has been used and no live page has been changed. Real listing media/video, final branding and measurement configuration are pending.
