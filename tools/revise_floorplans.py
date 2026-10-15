"""Apply the client's blue-ink floor-plan corrections (October 2026) to the MouveMedia originals.

Only the marked changes are made; walls, dimensions and every other label stay original:
- room labels renamed, covered deck outline, barn Dutch doors and entrance labels added;
- the printed area totals removed (the measurement disclaimer stays).
Outputs full-size PNG masters plus Letter PDFs matching the supplied MLS layout.
"""
from pathlib import Path
import math, sys
import numpy as np
from PIL import Image, ImageDraw, ImageFont
from scipy import ndimage as nd

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / 'From client/9055-SE-Eola-Hills-Rd (2)/_floorplan'
OUT = ROOT / 'output/floorplans'
FONT = Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / 'tmp/OpenSans.ttf'
SS = 4  # supersampling for anti-aliased text and arcs
INK = (0, 0, 0)
LINE = (94, 94, 94)  # thin outline grey used by the original porch/patio
BARN_TILT = 12.7  # barn sheet labels are drawn clockwise by this angle


def font(px, weight=400):
    f = ImageFont.truetype(str(FONT), px)
    f.set_variation_by_axes([weight, 100])
    return f


def size_for_cap(cap):
    return cap / 0.71  # Open Sans cap height is 0.71 em


class Sheet:
    def __init__(self, name):
        self.path = next(SOURCE.glob(name + '-*.png'))
        self.im = Image.open(self.path).convert('RGB')

    # -- erasing ---------------------------------------------------------
    def erase_text(self, center, half_w, half_h, angle=0.0):
        """Whiten every glyph whose centroid lies in the (rotated) label box."""
        cx, cy = center
        r = int(math.hypot(half_w, half_h)) + 30
        x0, y0 = max(cx - r, 0), max(cy - r, 0)
        a = np.array(self.im.crop((x0, y0, cx + r, cy + r)).convert('L'))
        ink = a < 250
        lab, n = nd.label(ink)
        t = math.radians(angle)
        kill = np.zeros_like(ink)
        for i, s in enumerate(nd.find_objects(lab), 1):
            if s[0].stop - s[0].start > 80 or s[1].stop - s[1].start > 80:
                continue  # walls and long lines are never label glyphs
            ys, xs = np.nonzero(lab[s] == i)
            gx, gy = xs.mean() + s[1].start + x0 - cx, ys.mean() + s[0].start + y0 - cy
            lx, ly = gx * math.cos(t) + gy * math.sin(t), -gx * math.sin(t) + gy * math.cos(t)
            if abs(lx) <= half_w and abs(ly) <= half_h:
                kill[s] |= lab[s] == i
        kill = nd.binary_dilation(kill, iterations=2) & ink
        patch = np.array(self.im.crop((x0, y0, x0 + a.shape[1], y0 + a.shape[0])))
        patch[kill] = 255
        self.im.paste(Image.fromarray(patch), (x0, y0))

    def white(self, box):
        ImageDraw.Draw(self.im).rectangle(box, fill='white')

    # -- drawing ---------------------------------------------------------
    def text(self, center, lines, cap, pitch=None, angle=0.0, weight=400, fill=INK):
        """Centre cap-height text lines on `center` (rotated clockwise by `angle`)."""
        f = font(round(size_for_cap(cap) * SS), weight)
        pitch = (pitch or cap * 1.7) * SS
        widths = [f.getlength(s) for s in lines]
        w = int(max(widths)) + 8 * SS
        h = int(pitch * (len(lines) - 1) + cap * SS) + 8 * SS
        layer = Image.new('L', (w, h), 0)
        d = ImageDraw.Draw(layer)
        for k, s in enumerate(lines):
            d.text((w / 2, 4 * SS + cap * SS + k * pitch), s, font=f, fill=255, anchor='ms')
        if angle:
            layer = layer.rotate(-angle, resample=Image.BICUBIC, expand=True)
        layer = layer.resize((max(1, round(layer.width / SS)), max(1, round(layer.height / SS))), Image.LANCZOS)
        x, y = round(center[0] - layer.width / 2), round(center[1] - layer.height / 2)
        self.im.paste(Image.new('RGB', layer.size, fill), (x, y), layer)

    def strokes(self, paths, width, fill=LINE):
        """Anti-aliased polylines given in sheet coordinates."""
        pts = [p for path in paths for p in path]
        x0, y0 = int(min(p[0] for p in pts)) - 10, int(min(p[1] for p in pts)) - 10
        x1, y1 = int(max(p[0] for p in pts)) + 10, int(max(p[1] for p in pts)) + 10
        layer = Image.new('L', ((x1 - x0) * SS, (y1 - y0) * SS), 0)
        d = ImageDraw.Draw(layer)
        for path in paths:
            d.line([((x - x0) * SS, (y - y0) * SS) for x, y in path], fill=255, width=max(1, round(width * SS)), joint='curve')
        layer = layer.resize((x1 - x0, y1 - y0), Image.LANCZOS)
        self.im.paste(Image.new('RGB', layer.size, fill), (x0, y0), layer)

    def polygon(self, pts, fill):
        ImageDraw.Draw(self.im).polygon([tuple(map(round, p)) for p in pts], fill=fill)

    def rename(self, center, half, new, cap, angle=0.0, above=None, pitch=None):
        """Replace a room name; `above` holds extra lines stacked over it."""
        self.erase_text(center, half[0], half[1], angle)
        lines = (above or []) + [new]
        pitch = pitch or cap * 1.7
        shift = (len(lines) - 1) * pitch / 2
        t = math.radians(angle)
        c = (center[0] + shift * math.sin(t), center[1] - shift * math.cos(t))
        self.text(c, lines, cap, pitch, angle)

    def remove_totals(self, title):
        self.white((980, 2625, 3020, 2875))
        if title:
            self.text((2000, 2664), [title], 36, weight=700)

    def save(self, name):
        OUT.mkdir(parents=True, exist_ok=True)
        path = OUT / name
        self.im.save(path, optimize=True)
        return path


def affine(src, dst):
    """Least-squares affine map from point pairs."""
    A = np.array([[x, y, 1] for x, y in src]); B = np.array(dst)
    m, *_ = np.linalg.lstsq(A, B, rcond=None)
    return lambda p: tuple(np.array([p[0], p[1], 1]) @ m)


def arc(cx, cy, r, a0, a1, n=48):
    return [(cx + r * math.cos(math.radians(a0 + (a1 - a0) * k / n)), cy + r * math.sin(math.radians(a0 + (a1 - a0) * k / n))) for k in range(n + 1)]


# --- Geometry measured on the 4000 x 3000 originals --------------------------
# Main floor (sheet 02): covered deck along the living/dining side, eyeballed
# per the client (no dimensions): den corner to the electrical-room/laundry wall.
DECK = [(169, 800), (169, 430), (1478, 430), (1478, 935)]
# Barn (sheet 01): four top-wall openings become 4 ft Dutch doors swinging out.
BARN_WALL_TOP, BARN_WALL_BOTTOM = 431, 454
DUTCH = [1291.5, 1797, 2293.5, 2843.5]
WINDOW_HALF = 115
DOOR = 176  # 4 ft at about 44 px/ft


def dutch_door_shapes(cx):
    left, right = cx - DOOR / 2, cx + DOOR / 2
    wall = [[(cx - WINDOW_HALF, BARN_WALL_TOP), (left, BARN_WALL_TOP), (left, BARN_WALL_BOTTOM), (cx - WINDOW_HALF, BARN_WALL_BOTTOM)],
            [(right, BARN_WALL_TOP), (cx + WINDOW_HALF, BARN_WALL_TOP), (cx + WINDOW_HALF, BARN_WALL_BOTTOM), (right, BARN_WALL_BOTTOM)]]
    frame = [[(left, BARN_WALL_TOP), (right, BARN_WALL_TOP)], [(left, BARN_WALL_BOTTOM), (right, BARN_WALL_BOTTOM)]]
    # Hinged on the right jamb, leaf open outward, quarter arc back to the left jamb.
    leaf = [(right, BARN_WALL_TOP), (right, BARN_WALL_TOP - DOOR)]
    swing = arc(right, BARN_WALL_TOP, DOOR, 270, 180)
    return wall, frame + [leaf, swing]


def barn_doors(sheet, m=lambda p: p, scale=1.0):
    for cx in DUTCH:
        wall, lines = dutch_door_shapes(cx)
        # Clear the window graphic inside the wall thickness.
        sheet.polygon([m(p) for p in [(cx - WINDOW_HALF - 1, BARN_WALL_TOP - 1), (cx + WINDOW_HALF + 1, BARN_WALL_TOP - 1), (cx + WINDOW_HALF + 1, BARN_WALL_BOTTOM + 1), (cx - WINDOW_HALF - 1, BARN_WALL_BOTTOM + 1)]], 'white')
        for poly in wall:
            sheet.polygon([m(p) for p in poly], INK)
        sheet.strokes([[m(p) for p in path] for path in lines], 2.4 * scale, INK)


def main():
    made = []
    # 1st floor: home, garage/RV bay and workshop.
    s = Sheet('02')
    s.rename((323, 938), (40, 8), 'DEN', 12, pitch=20)
    s.rename((1748, 1298), (40, 8), 'WALKWAY', 12, above=['COVERED'], pitch=20)
    s.rename((2537, 1551), (40, 8), '3-CAR GARAGE', 12, pitch=20)
    s.rename((2929, 1363), (40, 8), 'RV BAY', 12, pitch=20)
    s.rename((3295, 1159), (50, 8), 'HOBBY/FLEX SPACE', 12, above=['WOODWORKING SHOP /'], pitch=20)
    s.strokes([DECK], 2.2)
    s.text((823, 560), ['COVERED DECK'], 12)
    s.text((640, 1405), ['MAIN HOME', 'ENTRANCE'], 12, pitch=20)
    s.strokes([[(640, 1382), (640, 1343)]], 2.2, INK)
    s.polygon([(640, 1336), (633, 1350), (647, 1350)], INK)
    s.remove_totals('1ST FLOOR')
    made.append(s.save('01-1st-floor-home-garage-workshop.png'))

    # 2nd floor.
    s = Sheet('03')
    s.rename((674, 1722), (90, 20), 'OFFICE', 34, pitch=60)
    s.rename((1924, 1435), (70, 20), 'CATWALK', 34, pitch=60)
    s.remove_totals('2ND FLOOR')
    made.append(s.save('02-2nd-floor.png'))

    # Barn.
    s = Sheet('01')
    t = BARN_TILT
    s.rename((1301, 706), (74, 14), 'STALL', 24, t, pitch=40)
    s.rename((1806, 701), (74, 14), 'STALL', 24, t, pitch=40)
    s.rename((2866, 2011), (74, 14), 'STALL', 24, t, pitch=40)
    s.erase_text((2194, 1341), 64, 14, t)
    s.text((2199, 1318), ['BARN'], 56, angle=t, weight=600)
    barn_doors(s)
    s.text((2067, 205), ['DUTCH DOORS'], 24, angle=t)
    s.text((2350, 2480), ['DUTCH DOORS'], 24, angle=t)
    s.text((800, 1330), ['DOUBLE DOOR', 'ENTRANCE'], 24, pitch=40, angle=t)
    s.remove_totals('BARN')
    made.append(s.save('03-barn.png'))

    # All floors composite: same corrections at its scale.
    s = Sheet('04')
    # Upper floor: rename, then lift it to make room for the main-floor deck.
    s.rename((247, 856), (24, 5), 'OFFICE', 9, pitch=15)
    s.rename((573, 781), (24, 5), 'CATWALK', 9, pitch=15)
    s.erase_text((176, 1001), 30, 6)
    s.text((176, 1001), ['2nd floor'], 9)
    upper = s.im.crop((0, 380, 1100, 1040))
    s.white((0, 380, 1100, 1040)); s.im.paste(upper, (0, 210))
    main_m = lambda p: (0.75 * p[0] + 20.9, 0.75 * p[1] + 595)
    s.rename((263, 1298), (24, 5), 'DEN', 9, pitch=15)
    s.rename((1332, 1568), (30, 5), 'WALKWAY', 9, above=['COVERED'], pitch=15)
    s.rename((1924, 1758), (30, 5), '3-CAR GARAGE', 9, pitch=15)
    s.rename((2218, 1617), (30, 5), 'RV BAY', 9, pitch=15)
    s.rename((2492, 1464), (36, 5), 'HOBBY/FLEX SPACE', 9, above=['WOODWORKING SHOP /'], pitch=15)
    s.strokes([[main_m(p) for p in DECK]], 1.6)
    s.text(main_m((823, 560)), ['COVERED DECK'], 9)
    s.text(main_m((640, 1405)), ['MAIN HOME', 'ENTRANCE'], 9, pitch=15)
    s.strokes([[main_m((640, 1382)), main_m((640, 1343))]], 1.6, INK)
    s.polygon([main_m(p) for p in [(640, 1336), (633, 1350), (647, 1350)]], INK)
    s.erase_text((108, 1671), 30, 6)
    s.text((108, 1671), ['1st floor'], 9)
    barn_m = affine([(1035, 433), (3140, 437), (1028, 2300), (3131, 2310)], [(3041, 1433), (3770, 1270), (3185, 2079), (3913, 1918)])
    for c in [(3153, 1505), (3328, 1464), (3800, 1836)]:
        s.rename(c, (30, 5), 'STALL', 9, pitch=15)
    s.erase_text((3513, 1656), 32, 5)
    s.text((3513, 1651), ['BARN'], 20, weight=600)
    barn_doors(s, barn_m, 0.36)
    s.text(barn_m((2067, 215)), ['DUTCH DOORS'], 9)
    s.text(barn_m((2350, 2540)), ['DUTCH DOORS'], 9)
    s.text(barn_m((760, 1330)), ['DOUBLE DOOR', 'ENTRANCE'], 9, pitch=15)
    s.erase_text((3214, 2155), 30, 6)
    s.text((3214, 2155), ['barn'], 9)
    s.remove_totals(None)
    made.append(s.save('04-all-floors.png'))
    for p in made:
        print(p)
    for p in make_pdfs(made):
        print(p)


def make_pdfs(pngs):
    """Letter pages laid out like the supplied MLS PDFs: plan rotated 90 degrees, full height."""
    from reportlab.pdfgen import canvas
    from reportlab.lib.utils import ImageReader
    names = ['1st-Floor', '2nd-Floor', 'Barn', 'All-Floors']
    rotated = [ImageReader(Image.open(p).rotate(90, expand=True)) for p in pngs]
    out = []

    def page(c, image):
        c.drawImage(image, 9, 0, 594, 792)
        c.showPage()

    for name, image in zip(names, rotated):
        path = OUT / f'9055-Eola-Hills-Floor-Plan-{name}-Revised.pdf'
        c = canvas.Canvas(str(path), pagesize=(612, 792))
        c.setTitle(f'9055 SE Eola Hills Rd - Floor Plan - {name.replace("-", " ")}')
        page(c, image); c.save(); out.append(path)
    path = OUT / '9055-Eola-Hills-Floor-Plans-Revised-All-Pages.pdf'
    c = canvas.Canvas(str(path), pagesize=(612, 792))
    c.setTitle('9055 SE Eola Hills Rd - Revised Floor Plans')
    for image in rotated:
        page(c, image)
    c.save(); out.append(path)
    return out


if __name__ == '__main__':
    main()
