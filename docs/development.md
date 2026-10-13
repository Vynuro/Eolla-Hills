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

Browser checks use installed Chrome (or `CHROME_PATH`) and the running port-8085 site. They cover five viewport sizes, image loading, overflow, links, noindex, gallery filtering/lightbox, carousel, reduced motion and a real Elementor edit/save/frontend verification/restore. Screenshots go in `artifacts/screenshots/`.

The isolated WordPress instance uses `docker compose -p eola-updatecheck -f compose.yaml -f compose.update-test.yaml` and port 8086, without source-plugin mounts. `verify-bootstrap.php` exercises the one-installer dependency/package install. `make_update_fixture.py` creates a local next-version ZIP; `verify-wordpress.php` exercises an actual Plugin_Upgrader replacement while verifying that edited Elementor data survives byte-for-byte. Its GitHub package HTTP response is replaced with local fixture bytes; this test alone does not verify GitHub authentication or availability. Also tests package checksum, project/version mismatch, path traversal, permissions, file conflicts, backup integrity and lock release.

`verify-github.php` separately fetches the real published GitHub manifest and package and installs it on the isolated instance. On 2026-10-13 this passed for release 1.0.0, without a token (the repository is public). GitHub Actions built and published the release successfully. Browser checks at five viewport sizes, interactions, and the real Elementor edit/save/restore also passed. The update fixture preserved saved text, colours and responsive spacing. WordPress.org background update checks showed intermittent TLS warnings in one local run; the property-package update completed, and the real GitHub install subsequently passed. Hosting connectivity still needs checking when client access arrives.

## Taabeer reuse

`tools/reuse_taabeer.py` extracts six deployment methods and the dependency/kit setup routines from the original `D:\Taabeer` source. Runtime copies live in `eola-hills-updates/inc/trait-taabeer-deployment.php` and `inc/taabeer-foundation.php`. Package targeting is adapted from theme to plugin; dependency list narrowed to Elementor. The original updater reference is preserved under `reference/taabeer/`.

## Deployment boundary

GitHub releases distribute code; publishing a release does not deploy or publish the client's website. No hosting login has been used and no live page has been changed. Real listing media/video, final branding and measurement configuration are pending.

The 1.1.0 redesign uses 275 native Elementor elements, Cormorant Garamond and DM Sans, an immersive hero with photo switching, varied image compositions and an inset agent panel. The original local Elementor document was backed up before the authorized redesign reseed. Routine GitHub updates still preserve existing saved documents; the redesigned seed is used on a fresh installation.


The 1.2.2 refinement uses 310 native Elementor elements and the user's blue palette across the website. It adds the connected statistics panel, centered address/radar pill, redesigned hero, interactive feature panels, a highlighted barn/workshop note, and aligned interior imagery. The carousel now requests full-resolution media with Elementor's thumbnail_size setting; browser verification rejects upscaled small thumbnails. Native Elementor edit/publish/restore passed, and the pill icon/text centers and card hover lift/zoom were measured in the rendered page. Run visual checks after editor checks finish: editing regenerates Elementor CSS and can invalidate screenshots taken concurrently.


Final content review against the client's local DOCX removed authored promotional additions, including the residence tagline, new statistics descriptions, the architectural-character headline, the outdoor pace headline and the gallery introduction. Visible marketing copy now comes from the supplied document, with case, line breaks and punctuation adapted for layout. Navigation, gallery filters, accessibility labels, email actions and the requested Fair Housing label are functional labels; names, price, dimensions and contact information retain the supplied values. Final headings use Manrope SemiBold (600), body text uses regular Manrope, and native header columns center the navigation between brand and CTA.
