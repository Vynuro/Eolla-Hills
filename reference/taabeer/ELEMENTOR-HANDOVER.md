# TAABEER 1.2.2 - editing and handover

Release prepared on 13 October 2026. This theme uses Elementor Free and does not modify WordPress or official plugin source files.

## Editing pages and shared layouts

Use **Pages > Edit with Elementor** for brand, regional and legal pages. **TAABEER > Setup & editing** provides shortcuts to pages and archive templates. These shortcuts open the normal Elementor editor; they are not a replacement editor.

The shared header and footer are official Header Footer Builder records. Open **TAABEER > Header & footer** and edit the records named **TAABEER - Site-wide Header/Footer**. One shared pair serves the entire website. Older recovery layouts are not the active shared pair.

Headings, paragraphs, images, buttons and many containers use native Elementor widgets. Hero, collection grids, navigation, forms and dynamic content also use custom TAABEER widgets supplied by the theme. The website is **not entirely stock Elementor widgets**. Keep the Taabeer theme, Elementor and Header Footer Builder active. Removing only the GitHub updater does not remove content, subscriber records or these editing controls. Switching away from the theme removes its styling, custom widgets and newsletter workflow.

Edit native text and image controls normally. Use **Elementor > Site Settings** for global colours and typography. Explicit element settings take precedence over theme defaults. Text edits retain stored colours. For responsive changes, select Elementor's desktop/tablet/mobile preview and use the device control beside typography, spacing or size. Native container padding, margins, width and minimum height now also override the editorial defaults. Custom TAABEER widgets provide responsive Style controls for section padding, typography and relevant grid/form/hero settings. Some editorial layout defaults remain in the theme stylesheet.

The theme repairs a missing or unpublished default kit using Elementor's API while preserving existing kit settings. Check both the editor and frontend after publishing any layout changes.

## Journal

Open **Journal > All Stories > Edit with Elementor** for an individual story. Titles, slugs, excerpts, featured images and publication status remain normal WordPress fields. The listing template is linked from TAABEER setup. Published stories appear in its dynamic grid; the existing sample remains a draft.

Edit each story's SEO title, description and focus phrase in its normal Yoast panel. Archive SEO settings are under Yoast settings for the Journal content type. This release adds a Journal archive title/description and retains later client edits.

## Contact and newsletter

On the Contact page, select the TAABEER Contact widget in Elementor and set **Receive enquiries at**. Its visible details use separate editable widgets. The placeholder phone/email and final business details still need the client's approval.

On the homepage, select **TAABEER Newsletter** and set **Receive subscriber notifications at**. A blank value uses the WordPress administrator inbox. Form labels, consent text, messages, colours and field spacing are editable in Elementor. This is a custom widget for Elementor Free, not Elementor Pro's paid Form widget.

Subscribers are listed under **TAABEER > Newsletter subscribers**, with pending, confirmed and unsubscribed status. The workflow is:

1. Visitor enters an email and explicitly agrees to updates.
2. WordPress sends a confirmation link, valid for 48 hours.
3. Confirmation activates the subscription, sends a welcome/unsubscribe link and notifies the configured inbox.
4. Unsubscribe links and the administrator action stop the subscription.

Subscriber records remain in the WordPress database. Pending requests are cleaned up after 30 days through WP-Cron. Native **Tools > Export/Erase Personal Data** includes newsletter records. Forms use signed configuration, nonce checks, a honeypot and rate limiting.

No external mailing-list service is required for signup or notifications. This release does **not** include a bulk campaign composer or campaign delivery service. All messages use `wp_mail` and the hosting mail transport. Local tests intercept email; actual inbox delivery must still be tested on the live host. Configure the host's mail transport if delivery fails.

## SEO, indexing and consent

Published brand and legal pages have native Yoast titles, descriptions and focus phrases. Elementor's own serializer supplies WordPress page content for search and analysis. No SEO score is fabricated. A green Yoast analysis or search position is not guaranteed by metadata; final copy, photography and native editor analysis remain relevant. Existing public slugs are retained.

Privacy and Cookie Policy pages now explain the current forms, subscriber storage and consent behaviour. They are editable in Elementor and indexable, as requested. The client must confirm final business identity, provider and retention details. The text is a project policy draft, not a legal compliance certification.

Yoast's sitemap is at `/sitemap_index.xml`; legal pages are included when site indexing is enabled. Local staging retains its noindex setting. Draft stories, products and checkout pages remain unpublished. On production, keep **Settings > Reading > Discourage search engines** unchecked if indexing is intended. Indexability does not guarantee that a search engine will include a page.

Cookie preferences last up to 180 days. Optional Google Analytics loads only with consent; withdrawing consent disables it and removes accessible Analytics cookies. The measurement ID is entered in TAABEER setup. Search Console verification is managed through Yoast. Actual Analytics/Search Console account connections still require client access.

## Commerce, backups and updates

WooCommerce is installed and purchasing remains disabled. Shop, cart, checkout, account pages and sample products stay draft. Categories, Size/Colour/Material attributes and inactive UK/international delivery zones are prepared. Product prices, stock, variations, orders and delivery settings are managed in WooCommerce; Elementor controls their presentation.

Before enabling commerce, supply approved products, payment accounts, shipping rates, taxes and returns wording, then test successful/failed transactions, order emails, refunds and mobile checkout.

The foundation includes Elementor, Header Footer Builder/UAE, Yoast, WooCommerce, UpdraftPlus and Limit Login Attempts Reloaded. Daily WordPress backup schedules are configured. Client-owned remote backup storage, hosting daily backups and a restore test still need confirmation. Take backups and test compatibility before major upgrades; no implementation can guarantee every future third-party update.

**TAABEER > GitHub updates** checks and installs published theme releases. A Git push prepares a release; the current updater requires an install action in WordPress. It does not merge or reverse-sync live database edits into Git. Deleting it stops repository updates and leaves the installed theme/content intact.

One-time migrations save recovery metadata before changing the legal pages or extending the homepage. Subsequent admin visits do not reapply them. Routine theme releases preserve saved Elementor content; this release intentionally updates legal text and SEO metadata as requested. Theme file customisations should be kept in Git or a child theme because an update replaces parent-theme files.

## Verification and remaining checks

Local verification covers newsletter confirmation/unsubscribe/privacy/rate limiting/mail-failure handling, native mobile CSS generation, colour persistence, menu keyboard behaviour, cookie consent and expiry, content preservation, draft commerce and public-page SEO structure. Test emails were intercepted and never sent.

Browser-based desktop/tablet/mobile visual review, saving changes in the live Elementor interface, actual native Yoast indicator colours and real hosting email delivery remain unverified. Computer Use currently reports an unavailable Windows native pipe. Installing the release and completing those checks is required before final delivery sign-off. Client-approved photography, final contact details, legal business details, analytics ownership and backup destinations also remain to be supplied or confirmed.


## 1.2.1 header and Yoast analysis repair

Elementor base CSS now loads before theme layout defaults, with per-page Elementor settings following them. This repairs the shared logo/header alignment regression introduced in 1.2.0 and preserves editable spacing controls.

Open **Pages > All Pages** after installing this release. An administrator-only notice automatically calculates missing/stale analyses with the installed Yoast JavaScript engine. Wait for completion and select **Show updated scores** to refresh the native Yoast columns. **Recalculate analyses** can repeat the checks. The same integration is available on the Journal listing for published stories. Draft commerce content is excluded.

The calculation uses current rendered Elementor content, current focus phrase, SEO title/description and real browser title-width measurement. It stores the engine's results in Yoast's standard metadata and rebuilds the indexable. It rejects stale results when the content changes. No green score is hard-coded, and detailed editor recommendations can still differ where another plugin contributes editor-specific analysis data. Official Yoast/Elementor source files remain untouched.

Collections, Discover, Regions, Partnerships and Contact now have clearer introductory labels. Discover and Regions have a short explanatory section; Contact has a native Elementor accordion with useful enquiry guidance. The one-time migration retains recovery copies and does not rerun over subsequent client edits. Local installed-engine diagnostics improved these pages into the green SEO range; browser execution and live appearance still require confirmation after installing the release.


## 1.2.2 newsletter screen and expanded navigation

The Newsletter subscribers submenu now registers after its TAABEER parent. This ensures WordPress resolves the correct admin page handler. The subscriber list also handles empty/single-page pagination without a PHP deprecation warning.

Expanded navigation uses ivory links on the deep-green background, with gold hover/focus styling. Edit these independently of desktop links in the shared header's **TAABEER Navigation > Style > Expanded menu colours** controls. The update clears generated Elementor CSS once; stored page content and subscriber records are retained.
