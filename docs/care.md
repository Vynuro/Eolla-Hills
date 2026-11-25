# Eola Care - local implementation and operating limits

Independent **Eola Care** plugin and left-panel dashboard. It is included as a separate companion package in the local Git updater manifest. New installs stay inactive; code updates preserve activation state. All new plugin work remains local until the user authorizes a Git push.

## Private storage

Set `EOLA_CARE_BACKUP_DIR` to a writable directory **outside the document root** if automatic discovery cannot create one there. Public upload-folder storage is refused. Local Docker uses a dedicated private volume at `/var/eola-care`; it is not a web URL. Backups contain sensitive website files and database records; downloads require an authenticated administrator and a nonce.

The plugin supports single-site WordPress, up to 40,000 files / 2 GB, and requires PHP Zip and sufficient disk space. Symbolic links require manual review. Files outside the WordPress directory, including externally located wp-config.php, server configuration, email and other sites, are outside the snapshot. Hosting/CDN/backups on other systems are not covered.

## Backup and restore

- Manual backup runs in short resumable batches while the administrator keeps the dashboard open. Reloading pauses work; Resume continues a running job.
- Daily automatic backup (default 02:00) and security scan (default 04:00), site timezone, run in background WP-Cron batches without an open browser. Each runs at most once per day, never during a restore and never over a running manual task. The latest 7 automatic backups are kept (7/14/30 selectable); manual and recovery backups are never pruned, and old automatic copies expire only after a new successful backup.
- Every scheduled run is recorded with date, status, start/finish, file counts, flagged/skipped findings and errors. Dated daily care reports download as CSV or printable client HTML.
- A snapshot contains WordPress files plus tables belonging to the current database prefix. Cache, upgrade work folders and debug logs are excluded. Database records use an internal JSONL format, not a SQL export.
- Each completed ZIP is integrity-checked and recorded with a SHA-256 digest. Only completed snapshots appear in the list. There are download, restore and explicit confirmed-delete controls.
- A restore first makes a new recovery snapshot. The target must pass its checksum and match the original site and table prefix.
- Database restore builds staging tables and swaps them using one atomic RENAME TABLE operation. Existing tables are not dropped while they are being rebuilt. Foreign-key schemas are refused before restore starts; use hosting recovery for these installations.
- Snapshot files overwrite their matching paths. **Files added after the snapshot are not removed.** wp-config.php and the currently running care plugin remain unchanged so credentials and the recovery controller remain usable. This is a same-site recovery tool, not a clean-room malware rebuild or site migration tool.
- Visitors see maintenance during restoration; administrators can access recovery. A failed restore retains the recovery snapshot and error. Review the error and restore a known-good snapshot; do not treat a partially restored site as finished.
- Backups are online snapshots, not a transactionally consistent snapshot across files and every database table. Avoid content edits during backup/restore. For critical recovery, also retain a hosting-level backup.

Download copies off-server. This version does not upload to remote storage. WP-Cron runs only when the site receives requests, so on a quiet live site configure the hosting cron to call `wp-cron.php` every five minutes (local Docker uses the `scheduler` service for this). A server/disk loss can destroy both the site and its local backups. Private archive retention is manual through the visible delete controls. Plugin deactivation/deletion retains saved backups, scan results, audit log and quarantine.

## Security scan and quarantine

Scan covers the WordPress directory, comparing official core checksums when available and looking for executable uploads, encoded execution, request-to-shell patterns and selected web-shell markers. Code files above 2 MB or unreadable files are counted as skipped. A report states whether official checksums were available. No finding is automatically labelled proven malware.

Quarantine requires an exact typed path confirmation and an unchanged file hash from the most recent scan. It copies the file to private storage, verifies the copy, then removes the original. Recovery refuses to overwrite an existing file. Permanent deletion requires separate confirmation. wp-config.php and the recovery controller are not quarantined through this tool. WordPress DISALLOW_FILE_MODS is respected for destructive file actions.

This is a review and recovery tool, **not a complete antivirus, WAF, vulnerability database, penetration test or promise that all malware is detectable**. False positives and missed threats are possible. Hosting security, account protection, prompt WordPress/plugin updates and independent backups remain part of management.

## Release readiness

Use the recorded isolated local tests before handoff. Actual hosting permissions, timeouts, private storage, backup download and a staging restore must be checked on the client host before relying on this tool for live recovery. No live restore, file removal or security change has been made.
