# Floor-plan revisions — October 2026

Source: the client's three-page blue-ink scan (`From client/Floorplan Revise_3pgs_eola (4).pdf`) applied to the full-size MouveMedia/CubiCasa originals in `From client/9055-SE-Eola-Hills-Rd (2)/_floorplan/`. As requested, the plans were edited, not redrawn. Walls, dimensions, symbols and every unmarked label are original pixels.

CubiCasa numbered the sheets barn = "1st floor", main floor = "2nd floor", upper floor = "3rd floor". The revised files use the client's naming.

| Revised sheet | Original | Changes |
|---|---|---|
| 1st floor | `02-2nd_floor…` | ROOM → DEN; PORCH → COVERED WALKWAY; GARAGE (36'3") → 3-CAR GARAGE; GARAGE (41'11") → RV BAY; BASEMENT → WOODWORKING SHOP / HOBBY/FLEX SPACE; added MAIN HOME ENTRANCE label and arrow; added COVERED DECK outline (from the den corner to the electrical-room/laundry wall, depth eyeballed as the client instructed, no dimensions) |
| 2nd floor | `03-3rd_floor…` | ROOM → OFFICE; HALL (21'4") → CATWALK |
| Barn | `01-1st_floor…` | GARAGE → BARN (large type); three STORAGE → STALL; the four stall-side window openings became 4 ft Dutch doors; DUTCH DOORS labels on both sides (the three existing doors are the other Dutch doors, seven in total, matching the amenities sheet); DOUBLE DOOR ENTRANCE label at the large side opening |
| All floors | `04-all_floors…` | Same corrections at overview scale. The upper floor was moved up to make room for the deck. Floor captions renamed 1st floor / 2nd floor / barn |

On every sheet, the printed area block (`TOTAL: 3764 sq. ft`, per-floor and excluded areas) is removed, per the scan's "Remove" note. The "measurements deemed highly reliable but not guaranteed" disclaimer stays. Single sheets carry a bold `1ST FLOOR` / `2ND FLOOR` / `BARN` title in its place. The listing's 3,018 sq ft remains the only stated area.

## Rebuild

```
python tools/revise_floorplans.py path/to/OpenSans.ttf   # default: tmp/OpenSans.ttf
```

Labels use Open Sans, CubiCasa's typeface (OFL, Google Fonts), matched to the original cap heights and the barn sheet's 12.7° label angle. Outputs go to the ignored `output/floorplans/`:

- `01-1st-floor-home-garage-workshop.png`, `02-2nd-floor.png`, `03-barn.png`, `04-all-floors.png` (4000 × 3000 masters)
- `9055-Eola-Hills-Floor-Plan-<sheet>-Revised.pdf`: Letter pages laid out exactly like the client's supplied MLS PDFs (plan rotated 90°, full page height)
- `9055-Eola-Hills-Floor-Plans-Revised-All-Pages.pdf`: all four sheets in one file

The theme uses the PNG masters byte-for-byte as `floorplan-1`…`floorplan-4` (1st floor, 2nd floor, barn, all floors). The booklet uses the same files on pages 9–11.
