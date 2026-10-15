# 12-page property booklet

Local output: `output/pdf/9055-SE-Eola-Hills-Rd-Booklet.pdf`. Rebuild with `python tools/build_booklet.py` (ReportLab, Pillow and fontTools; reads the RMLS description DOCX from `From client/`). Run `tools/revise_floorplans.py` first if the floor plans change. Client originals stay local and the PDF is not committed.

Letter portrait, 12 pages: original high-resolution property photographs, supplied combined brokerage logo, Montserrat and brand purple. Property prose is excerpted from the supplied RMLS description; amenity bullets are from the client's amenities. No stock photographs or new property claims are added.

The builder prefers the full-resolution `9055-SE-Eola-Hills-Rd (2)` delivery for photos. Booklet photographs use 300-DPI derivatives at their placed size, keeping the PDF near 17 MB. Body and semibold heading fonts have distinct embedded font names so both weights render correctly.

1. Cover using the client's favourite exterior photograph.
2. Property/acreage overview and listing facts.
3. Living room, catwalk and family room.
4. Kitchen and dining.
5. Primary suite, bath and office.
6. Exterior, covered deck and grounds.
7. Monitor-style barn.
8. Garage/RV bay and detached workshop.
9. 1st-floor plan (home, covered deck, garage, RV bay and workshop).
10. 2nd-floor plan.
11. Barn plan.
12. Brokerage branding, listing address, RMLS, agent contacts and license numbers.

Pages 9–11 use the client-corrected floor plans described in `floor-plan-revisions.md`.

## Before commercial printing

- Printer trim size, bleed and colour requirements. Letter portrait without bleed suits office/digital printing and PDF sharing.
- A print QR code needs the client's confirmed destination URL.
