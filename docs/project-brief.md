# Eola Hills project brief

Prepared 2026-10-13 from the supplied Word document and client message. Requirements below are an intake record, not a confirmed hosting audit or final maintenance agreement.

## Implementation direction

Build an Elementor-friendly landing page after checking the current website. Prefer native Elementor controls for editable content and layout. Put required custom behavior in a dedicated Eola Hills plugin, adapting the Taabeer deployment approach to the chosen architecture.

PHP/CSS/JavaScript remain editable through Git. Elementor page content, media and settings are stored in WordPress, not automatically synchronized by a push. Routine code updates must preserve editor changes, and site content needs separate backups.

## Supplied property details

| Field | Client-supplied value |
| --- | --- |
| Address | 9055 SE Eola Hills Rd, Amity, Oregon |
| Price | $1,499,000 |
| Bedrooms / bathrooms | 3 / 3 |
| Approximate living area | 3,112 sq ft |
| Land | 4.89 acres |
| Branding | Wine Country Properties + eXp |
| Listing team | Linda Lindsay and Anita Borgeas |
| Linda | Broker & Realtor; (503) 720-1805; linda.lindsay@exprealty.com |
| Anita | Principal Broker; other contact details pending |
| Suggested URL path | `/9055-se-eola-hills-rd` |

These are draft listing details, not independently verified facts. Final amenities and current price/availability need client confirmation.

## Landing-page structure

1. Hero: address, "Unmistakably Special", facts, professional exterior/aerial photo and tour/highlights buttons.
2. Property highlights: acreage, custom home, barn/workshop and outdoor living.
3. Home: living room, kitchen, primary suite and architectural detail photographs.
4. Land: landscape/aerial image and supplied amenity highlights; confirm RV capability wording.
5. Barn and workshop/studio: two strong images with supporting copy.
6. Outdoor lifestyle: deck, pergola, mature trees and setting.
7. Property video: clearly marked placeholder until professional video arrives.
8. Gallery: strongest 10-15 images, optionally linking to a full gallery.
9. Property details: supplied facts plus confirmed amenities.
10. Listing team: real headshots, contacts, approved logos and requested Fair Housing branding.
11. Final CTA: private showing and contact listing team.

Confirm the destination of tour buttons. Email/phone links are an initial option; a click is not a confirmed booking. The flyer QR must link directly to the property page, with campaign parameters to distinguish QR traffic.

SEO title: `9055 SE Eola Hills Rd, Amity OR | Eola Hills Luxury Property`.

Meta description: `Explore 9055 SE Eola Hills Rd in Amity, Oregon. A distinctive 4.89-acre property with a custom home, barn, workshop, outdoor living and scenic surroundings.`

## Management expectations

The client describes an informational website without financial transactions, wants useful visitor/SEO information, and proposes reviewing actual workload and reporting usefulness before the end of the month. The Word document also requests weekly reports for Linda. Record both expectations and confirm the reporting cadence at kickoff.

Proposed scope, separate from the landing-page build:

- Initial audit of hosting coverage, platform/plugins, backups and existing analytics.
- Updates with compatibility checks, backup/restore checks, availability and contact-link checks, plus an agreed minor-edit allowance.
- Short weekly report with observations and next actions; track time spent on maintenance and reporting.
- Month-end review of actual effort, useful metrics and the ongoing fee. No fee or unlimited-support commitment has been set.

| Client question | Proposed measurement |
| --- | --- |
| How many visits/hits? | Page views, sessions and users, labeled separately |
| Where do visitors come from? | Session source/medium, campaign and approximate location when available |
| How long do they stay? | Average engagement time, not a claim of exact reading time |
| Do they return? | New/returning users within available measurement limits |
| Does the QR help? | Tagged flyer campaign visits |
| Are photos/video useful? | Gallery interactions and video events verified for the chosen widgets/player |
| Are visitors interested? | Tour CTA, phone and email clicks; clicks do not prove enquiries or bookings |
| Is SEO improving? | Search Console impressions, clicks, queries, CTR, average position and indexing checks |

Use client-owned analytics accounts and avoid duplicate tracking. GA4 supports acquisition and returning-user reporting; engagement time measures active engagement. Browser restrictions and user choices affect measurement. New tracking cannot recreate uncollected historical data.

References: [Google acquisition reports](https://support.google.com/analytics/answer/14731736?hl=en), [user metrics](https://support.google.com/analytics/answer/12253918?hl=en), [engagement time](https://support.google.com/analytics/answer/11109416?hl=en).

## Hosting review

The client mentions hosting.com; their actual provider and plan remain unconfirmed. The public [WordPress offering](https://hosting.com/wordpress/) advertises backups and optional updates, while its [maintenance add-on](https://hosting.com/security/wordpress-maintenance/) advertises managed updates and monitoring. Check the purchased plan before duplicating covered work or estimating fees. Public descriptions do not prove these services are active on the account.

## Missing inputs

- Confirm whether the supplied property domain should show the landing page at `/` or the document's suggested path.
- WordPress admin access if applicable, hosting login URL/provider and plan, and staging availability.
- Existing theme, Elementor edition/license and installed plugins.
- Professional photographs/video, agent headshots and approved branding.
- Confirmed amenities, current listing facts, Anita's contacts and tour-button destination.
- Existing Analytics/Search Console access and ownership.
- Management start date, report recipient, month-end review date and content-edit allowance.

## Work completed

Read the Word document for content and copied the Taabeer updater plus related notes into `reference/taabeer/`. Verified all five copied plugin files against their local source using SHA-256 hashes.

The user supplied `https://www.9055eolahills.com/`. Public requests on 2026-10-13 returned HTTP 404 from the HTTPS www/non-www homepages, HTTP www homepage and `https://www.9055eolahills.com/wp-login.php`. Responses identify LiteSpeed. This does not establish the hosting provider or prove WordPress is absent; hosting/document-root and installation details require account access.

No client login, confirmed platform identification, analytics installation, live change, new deployable plugin or landing-page build has occurred.
