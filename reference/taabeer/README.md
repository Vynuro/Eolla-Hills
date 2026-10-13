# Taabeer source reference

Copied from `D:/Taabeer` on 2026-10-13 at repository HEAD `ab163b522169ad4dc15c6fd50e2b4fca7600c312`. The plugin identifies itself as version 1.0.1. Original licensing and attribution are retained.

This unchanged reference is outside the active WordPress plugins directory. It is not an Eola Hills release. Do not install it on this client's site as-is: it installs and can activate the Taabeer theme and run its demo importer.

Elementor widgets and page design belong to the Taabeer theme, not this updater. The copied handover and deployment notes describe that earlier project only.

Before reuse:

- Inspect the existing website and retain its theme where appropriate for a single landing page.
- Create separate Eola Hills namespaces, options, hooks, repository settings and release assets.
- If the new deliverable is a plugin, adapt the theme-specific installer, permissions, package validation, backups and update handling. Renaming alone is insufficient.
- Remove Taabeer theme activation and demo-import behavior.
- Make initial page creation explicit and repeat-safe. Routine updates must preserve client-edited Elementor content.
- Test installation, updates, rollback and content preservation on staging.

The existing flow publishes a release after a push, then requires an installation action in WordPress. No Eola Hills release pipeline or automatic live deployment is configured.
