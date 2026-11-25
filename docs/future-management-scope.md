# Deferred management scope - user instruction, October 14, 2026

Implemented November 2026 in Eola Care (10 page renders per 60 seconds → 15-minute block, verified search crawlers exempt). See `management.md`.

Requested: a short burst of 10 site opens from one IP triggers a 15-minute block, with dated client reporting. Clarify the exact time window and whether "opens" means top-level page loads or HTTP requests when implementation is authorized. Ten asset requests are normal for a single page; shared NAT/proxy IPs can represent multiple legitimate visitors.

Implementation review should cover trusted proxy/CDN IP resolution, IPv6, excluded administration/recovery paths, shared-IP false positives, explicit allowlisting, block expiry, bounded counters and storage retention. Prefer enforcement at the hosting/CDN/firewall layer where possible; PHP-only blocking still invokes the web server/PHP and does not prevent volumetric attacks.

Report actual blocked IP counts, blocked requests, time, reason and expiry. Do not label all blocked IPs as bots/hackers. Do not invent bandwidth/CPU/load savings: show such figures only if hosting measurements support them. Distinguish estimated avoided application requests from measured server load changes.

Current Analytics already has ingestion endpoint abuse limits. Those are not site-wide IP protection and do not fulfill this deferred feature.
