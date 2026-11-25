# Client delivery review — October 13, 2026

## Recheck — October 14, 2026

Rechecked the complete folder, all three ZIP manifests, PDF page counts/content and the four floor-plan images in the additional MouveMedia delivery. `9055-SE-Eola-Hills-Rd (2).zip` contains 87 JPEGs, four floor-plan PNGs and two text files (93 files, 450,230,779 bytes). It is a higher-resolution delivery of the same named photo/plan set as `9055-SE-Eola-Hills-Rd[3600].zip` (124,606,646 bytes). For example, the preferred exterior photo 03 is 6047 × 4021 instead of 3600 × 2394; the four plans are 4000 × 3000 instead of 3600 × 2700. Use the larger originals for subsequent print production; their presence does not establish that requested plan corrections have been made.

The four larger plans were visually inspected: they are clean drawings without the client's blue-ink annotations and still print 3,764 sq ft. No three-page blue-ink scan, separately identified MLS revision file, property booklet PDF or video was found in the folder or archives. The property amenities PDF has two pages; the remaining PDFs are branding/logo artwork and historical market reports. The RMLS description DOCX is listing copy, not an annotated floor-plan file.

A prior 12-page booklet PDF is **not required to create a new booklet** from the supplied property copy, photos and branding. Request it only if the task is to revise an existing booklet or preserve its layout/additional content. Accurate patio, door and label revisions still need the annotated scan or equally specific confirmed instructions. Final print export also needs printer size/bleed requirements.

The complete `From client` delivery was inventoried, including both ZIP archives. The property ZIP's 93 files and the branding ZIP's 71 non-system files match their extracted copies byte-for-byte. Original deliveries remain local and ignored by Git. Only selected website assets are included in the plugin.

## Reviewed material

- 87 property JPEGs, including staged/unstaged alternatives, interiors, outbuildings, grounds and aerials. All photographs were reviewed through labelled contact sheets. The website uses 18 selected unstaged photographs across sections and 15 distinct gallery entries.
- Four original 3600 × 2700 floor-plan PNGs, visually reviewed and included without altering printed dimensions or totals.
- Client-selected exterior WhatsApp image: this matches photo 03 in the high-resolution set. Use the higher-resolution original for the hero.
- Linda's WhatsApp headshot: the user confirmed the identity. Anita's supplied contact card provides her portrait, correct Borgaes spelling, phone (503) 550-8050 and anita@wcpcompanies.com.
- Client RMLS description DOCX: full and shortened listing copy reviewed. Current facts include 3,018 sq ft, 3 bedrooms, 3 full baths, 4.89 acres, a 1,498 sq ft garage/RV bay, 2,304 sq ft barn and 435 sq ft heated workshop.
- Two-page amenities PDF: both pages read and visually reviewed. Native accordion groups expose the key amenities; the original complete PDF is available from the page.
- Branding guide: visually reviewed because its text is outlined. Purple #4B5598, green #55A044, grey #8C8C8C, Montserrat and optional Lakeside script. The UI uses Montserrat SemiBold headings and regular body text; smaller text uses a darker grey for readability.
- Supplied logo variants: web, print and press artwork, stacked/horizontal, regular/reversed, eXp-only and combined brokerage logos. Original transparent combined web logos are used in the header, team section and footer.
- Letterhead and envelope DOCX files were read. These are corporate stationery and are not published as listing content. The letterhead also confirms Anita Borgaes spelling.
- Nine 2022/2023 market-report PDFs were text-extracted and reviewed for identity/context. They are historical regional/national rental-market collateral, not current property listing facts, and are not added to this page.
- The Mouve Media delivery licence was read. Selected photography is used for this specific active listing; the original archives and unrelated corporate collateral are not republished.

## User confirmations

- Use the client's preferred exterior image as the hero.
- RMLS #644545554.
- Anita Borgaes, license #200211111; Linda Lindsay, license #931000172.
- The headshot ending `11.08.50 PM.jpeg` is Linda; use Anita **Borgaes** rather than the old document's Borgeas spelling.
- Use 3,018 sq ft from the current RMLS/amenities documents. The floor-plan overview prints a 3,764 sq ft total; the user explicitly requested all original floor plans remain included. Keep them unchanged and state the difference beside them.
- Work locally, push to GitHub, and let the user install the update on the existing live site.

## Remaining material

No professional video or analytics account access was found in the complete folder or archives. The supplied Anita portrait is only available inside a small contact-card image; a standalone higher-resolution headshot would improve it. Price remains $1,499,000 from the original client landing-page document; the newer documents do not provide a replacement price.

## Content and update behavior

Property marketing copy is taken from the original landing-page document and the supplied RMLS/amenities documents. New UI labels name actual features, document actions and gallery filters. The area-discrepancy note is a factual disclosure of the two supplied sources, not an invented property claim.

Version 2.0.0 moves the design into an Eola Hills theme with one custom GitHub updater. A single Sync from GitHub action backs up old code and Elementor data, migrates the existing page to the theme, applies changed client content automatically and retires the old property plugin. Code-only releases retain saved page edits; changed template/media deliveries replace the managed page with a restorable backup.
