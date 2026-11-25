# Website management (ordered November 2026)

The client ordered the management package: website security, analytics reports and updates to existing images/content. New pages, designs or features are not included.

Everything is installed and updated by **Tools → Eola Hills Updates → Sync from GitHub**, the same as the theme. Local and live run the same plugins and settings.

| Plugin | Source | What it does |
|---|---|---|
| Eola Care 1.2 | This repository | Daily private backup (02:00) and security scan (04:00), 7 automatic backups kept. Visitor rate protection and hacking-attempt blocking. Report every 3 days. Daily backup/scan email. |
| Eola Analytics 1.2 | This repository | General analytics, no consent popup. Visitors, visits, page views, returning visits, sources, pages, devices. |
| Limit Login Attempts Reloaded | WordPress.org | Locks out repeated wrong passwords (its defaults: 4 tries, 20-minute lockout). |
| Eola Hills Updates 2.3 | This repository | Installs/updates the theme, itself, both Eola plugins and the WordPress.org plugin. |

New Eola plugins start active on first install. Afterwards, Sync keeps whatever on/off state the owner chooses. Limit Login Attempts Reloaded is installed and activated once and is never forced back on if the owner turns it off. If WordPress.org is unreachable it is skipped without blocking the Sync, and retried on the next one.

## Emails and reports

Every email goes to the **WordPress administration email** (Settings → General) and the **developer, ma9440863@gmail.com**, always. Extra addresses can be added in Eola Care → Website report. To change the developer address, define `EOLA_CARE_DEVELOPER_EMAIL` in wp-config.php.

- **Daily**: after the 04:00 scan, a short "Daily backup and security" email. The subject starts with "Action needed" if a task failed or the scan flagged files. If a task fails, the email goes out straight away.
- **Every 3 days at 08:00**: the website report for the previous three days:
  - visitors;
  - hacking attempts blocked, bots blocked, spam comments, login lockouts and the latest blocked IPs;
  - each day's backup and scan;
  - WordPress/PHP/update/HTTPS status.

  Reports are also archived privately and listed for download in Eola Care. **Save and send a report now** sends one immediately.
- PDF: download the report (.html) and open it in a browser → Print → Save as PDF, or run `node tools/report-to-pdf.cjs report.html`.

Mail uses the hosting server's PHP mail. If emails never arrive or land in spam, install an SMTP plugin with a real mailbox.

## Security measures

- **Visitor rate protection**: an IP that opens more than 10 pages within 60 seconds is blocked for 15 minutes (HTTP 429).
  - Only WordPress page renders count, not images or CSS.
  - Logged-in users, allowlisted IPs and verified Google/Bing crawlers (reverse and forward DNS) are never blocked.
  - Unblock or allowlist IPs in Eola Care.
- **Hacking-attempt blocking**: these requests block the IP for 15 minutes straight away:
  - requests for secret or exploit files such as `.env`, `.git`, `wp-config.php` backups, `.sql`, phpMyAdmin, phpunit `eval-stdin`, shell scripts, `.php` under uploads/plugins, or `../`;
  - `?author=` username scans.
- **XML-RPC disabled**: refused with 403, a common password-guessing route. Not used by this site.
- **REST user list hidden** from visitors who are not logged in.
- **Spam comments**: comments WordPress marks as spam, and comment posts to closed pages, are counted.
- **Daily file scan**:
  - compares WordPress core against official checksums;
  - flags executable files in uploads, encoded execution and web-shell markers.

  Findings are review flags; nothing is deleted automatically.

Counters (`eola_care_events`) keep 90 days; block history (`eola_care_blocks`) keeps 60 days. This is application-level protection. It does not stop network floods, which need hosting/CDN firewalls. The live site runs on LiteSpeed with no CDN, so `REMOTE_ADDR` is the visitor's IP.

## Analytics: general vs detailed

Collection is unchanged and complete, so unlocking later shows full history (90-day retention).

- **Shown**: visitors, sessions, page views, returning sessions, sessions over time, sources, pages and devices.
- **Locked**: active time, contact clicks, campaigns/QR, section attention, button/gallery/floor-plan/video/PDF interactions, and CSV and daily exports. Locked panels show **Unlock this feature** with a **Contact developer** email button.
- **To unlock**: add `define( 'EOLA_ANALYTICS_UNLOCKED', true );` to wp-config.php.

## Live rollout and checks

1. **One-time:** the live updater predates self-update, so upload `eola-hills-updates-2.3.0.zip` (Plugins → Add New → Upload → Replace current with uploaded). Later updater versions update themselves during Sync.
2. Click **Sync from GitHub**. Then confirm in Plugins that Eola Analytics, Eola Care and Limit Login Attempts Reloaded are active.
3. In Settings → General, check the administration email and set the timezone to Los Angeles.
4. In Eola Care:
   - no storage error should show (backups are kept outside the web root);
   - click **Save and send a report now** and confirm the email arrives.
5. Recommended: a hosting cron job for `wp-cron.php` every 5 minutes, so tasks run on time when the site is quiet.

## Tests

- `node tools/management-browser-check.cjs`: general analytics with locked panels, the Eola Care screens, and the locked export endpoint.
- `wp eval-file tools/verify-management.php foundation|report|daily` on 8085:
  - Limit Login Attempts is installed and not forced back on;
  - report recipients and sections are correct;
  - one daily email per day.
- `tools/verify-companions.php` on the fresh 8087 site. It checks that the Eola plugins and Limit Login Attempts start active, and that updates keep the owner's on/off choice.
- The 8087 compose has no source bind mounts. Never install or delete plugins on a site that bind-mounts this repository.
