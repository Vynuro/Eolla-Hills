"""12-page property booklet: client copy/photos and the client-corrected floor plans."""
from pathlib import Path
import json, re
from zipfile import ZipFile
from xml.etree import ElementTree as ET
from xml.sax.saxutils import escape
from reportlab.pdfgen import canvas
from reportlab.lib.colors import HexColor, white
from reportlab.lib.utils import ImageReader
from reportlab.platypus import Paragraph
from reportlab.lib.styles import ParagraphStyle
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from fontTools.ttLib import TTFont as Font
from fontTools.varLib.instancer import instantiateVariableFont
from PIL import Image, ImageOps
ROOT=Path(__file__).resolve().parents[1]
MEDIA=ROOT/'wordpress/wp-content/themes/eola-hills/assets/media'
TMP=ROOT/'tmp/pdfs'; TMP.mkdir(parents=True,exist_ok=True)
OUT=ROOT/'output/pdf'; OUT.mkdir(parents=True,exist_ok=True)
manifest=json.loads((MEDIA/'manifest.json').read_text())
for weight,name in [(400,'Body'),(600,'Semi')]:
    path=TMP/f'montserrat-{weight}.ttf'
    if not path.exists() or Font(path)['name'].getDebugName(6)!=f'EolaMontserrat{weight}':
        font=Font(ROOT/'wordpress/wp-content/themes/eola-hills/assets/fonts/montserrat.ttf')
        instantiateVariableFont(font,{'wght':weight},inplace=True)
        # Distinct internal font names prevent PDF font-cache collisions between weights.
        for record in list(font['name'].names):
            if record.nameID in (1,3,4,6): font['name'].setName(f'EolaMontserrat{weight}',record.nameID,record.platformID,record.platEncID,record.langID)
        font.save(path)
    pdfmetrics.registerFont(TTFont(name,str(path)))
W,H=612,792; M=42; CW=W-2*M
BLUE=HexColor('#4B5598'); INK=HexColor('#25232B'); MUTED=HexColor('#64636D'); PALE=HexColor('#F1F2F8')
pdf=OUT/'9055-SE-Eola-Hills-Rd-Booklet.pdf'
c=canvas.Canvas(str(pdf),pagesize=(W,H)); c.setTitle('9055 SE Eola Hills Rd - Property booklet'); c.setAuthor('Wine Country Properties Group')
tree=ET.fromstring(ZipFile(ROOT/'From client/9055_SE_Eola_Hills_RMLS_Descriptions.docx').read('word/document.xml'))
ns={'w':'http://schemas.openxmlformats.org/wordprocessingml/2006/main'}
paras=[''.join(t.text or '' for t in p.findall('.//w:t',ns)) for p in tree.findall('.//w:p',ns)]
source='\n'.join(paras)
def para_start(prefix): return next(p for p in paras if p.startswith(prefix))
def sentences(prefix): return re.split(r'(?<!Mt\.)(?<=[.!])\s+',para_start(prefix))
def text(s,x,y,w=CW,size=10.5,color=INK,font='Body',leading=None):
    p=Paragraph(escape(s).replace('\n','<br/>'),ParagraphStyle('p',fontName=font,fontSize=size,leading=leading or size*1.5,textColor=color))
    _,h=p.wrap(w,700); assert y-h>39,(s[:70],y,h); p.drawOn(c,x,y-h); return y-h
def title(kicker,heading):
    text(kicker.upper(),M,748,size=9,color=BLUE,font='Semi')
    return text(heading,M,719,size=30,font='Semi',leading=35)
def photo(key,x,y,w,h,contain=False):
    path=ROOT/'From client'/manifest[key]['source']
    # The full-resolution MouveMedia delivery uses the same photo/plan names.
    # Prefer it for print; keep brokerage assets on their original source path.
    relative=Path(manifest[key]['source'])
    if relative.parts and relative.parts[0]=='9055-SE-Eola-Hills-Rd[3600]':
        original=ROOT/'From client'/'9055-SE-Eola-Hills-Rd (2)'/Path(*relative.parts[1:])
        if original.is_file(): path=original
    if not path.exists(): path=MEDIA/manifest[key]['file']
    image=ImageOps.exif_transpose(Image.open(path)); iw,ih=image.size
    source=ImageReader(image)
    if path.suffix.lower() in ('.jpg','.jpeg'):
        # Keep 300 DPI at the placed size without embedding oversized originals.
        # Resampling affects this derivative only; client source files are untouched.
        placement=min(w/iw,h/ih) if contain else max(w/iw,h/ih)
        factor=min(1,placement*300/72)
        dimensions=(max(1,round(iw*factor)),max(1,round(ih*factor)))
        cached=TMP/f'{key}-{dimensions[0]}x{dimensions[1]}-{path.stat().st_mtime_ns}.jpg'
        if not cached.exists():
            image.resize(dimensions,Image.Resampling.LANCZOS).convert('RGB').save(cached,'JPEG',quality=93,optimize=True)
        source=str(cached)
    if contain:
        scale=min(w/iw,h/ih); dw,dh=iw*scale,ih*scale
        c.drawImage(source,x+(w-dw)/2,y+(h-dh)/2,dw,dh,mask='auto')
    else:
        scale=max(w/iw,h/ih); dw,dh=iw*scale,ih*scale
        c.saveState(); p=c.beginPath();p.rect(x,y,w,h);c.clipPath(p,stroke=0);c.drawImage(source,x+(w-dw)/2,y+(h-dh)/2,dw,dh,mask='auto');c.restoreState()
def footer(n,dark=False):
    c.setFillColor(white if dark else MUTED);c.setFont('Body',7)
    c.drawString(M,23,'WINE COUNTRY PROPERTIES GROUP  |  eXp REALTY');c.drawRightString(W-M,23,f'9055 SE EOLA HILLS RD  /  {n:02d}')
    c.showPage()
def bullets(items,x,y,w=CW):
    for item in items:
        c.setFillColor(BLUE);c.circle(x+2,y-7,2,fill=1,stroke=0);y=text(item,x+15,y,w-15,size=10)-12
    return y
# 01 / Favourite high-resolution exterior; no text baked into imagery.
c.setFillColor(BLUE);c.rect(0,0,W,H,fill=1,stroke=0)
photo('logo-reverse',M,704,260,48,True)
text('9055 SE EOLA HILLS RD',M,653,size=25,color=white,font='Semi')
text('AMITY, OREGON',M,611,size=10,color=white)
photo('hero',0,154,W,416)
text('Luxury living in the heart\nof Oregon Wine Country!',M,124,size=23,color=white,font='Semi',leading=28)
footer(1,True)
# 02 / Exact RMLS opening description.
title('The property','4.89 acres.\nOregon Wine Country.')
photo('aerial',M,343,CW,268)
text(' '.join(sentences('Luxury living')[:2]),M,313,size=11.5)
c.setFillColor(PALE);c.rect(M,91,CW,76,fill=1,stroke=0)
for x,value,label in [(M+20,'3','BEDROOMS'),(M+145,'3','FULL BATHS'),(M+266,'3,018','SQ FT'),(M+411,'2000','BUILT')]:
    text(value,x,150,w=110,size=24,font='Semi',color=BLUE);text(label,x,116,w=110,size=8)
footer(2)
# 03
title('Living room','Architecture & light')
photo('living',M,307,CW,342)
text(' '.join(sentences('Luxury living')[3:]),M,278,size=10.5)
photo('detail',M,60,245,105);photo('family-room',M+263,60,265,105)
footer(3)
# 04
title('Kitchen & dining','The well-appointed kitchen')
photo('kitchen',0,319,W,340)
text(sentences('The well-appointed')[0],M,289,size=12)
bullets(['Large center island w/ storage seats four','Walk-in pantry w/ storage shelves','Open to living room & kitchen','Full-light exterior door opens to covered deck'],M,180)
footer(4)
# 05
title('Bedrooms, baths & office','The primary suite')
photo('bedroom',M,389,CW,270)
photo('primary-bath',M,197,254,174);photo('office',M+274,197,254,174)
text(' '.join(sentences('The well-appointed')[2:]),M,174,size=10.5)
footer(5)
# 06
title('Exterior','The Wine Country setting')
photo('terrace',M,371,CW,290);photo('grounds',M,170,254,183);photo('landscape',M+274,170,254,183)
text(sentences('Professionally landscaped')[0],M,145,size=11)
footer(6)
# 07
title('Monitor-style barn','2,304 sq ft')
photo('barn',M,386,CW,273);photo('barn-interior',M,180,254,188)
text(para_start('Monitor-Style Barn:').split(': ',1)[1],M+276,367,252,size=10.5)
text('48\' x 48\' concrete pad',M,141,size=21,font='Semi',color=BLUE)
text('Power | Water | Bathroom w/ shower | Workshop area',M,100,size=9.5)
footer(7)
# 08
title('Garage, RV bay & workshop','Exceptional versatility')
photo('garage',M,434,254,213);photo('workshop',M+274,434,254,213)
text('1,498 SF garage & RV bay',M,402,254,size=16,font='Semi',color=BLUE)
text(para_start('Detached Garage & RV Bay:').split(': ',1)[1],M,348,254,size=10.5)
text('435 SF workshop / flex space',M+274,402,254,size=16,font='Semi',color=BLUE)
text(para_start('Detached Workshop/Flex Space:').split(': ',1)[1],M+274,348,254,size=10.5)
footer(8)
# 09-11 / MouveMedia plans with the client's blue-ink corrections (tools/revise_floorplans.py).
for n,(key,heading,summary) in enumerate([
    ('floorplan-1','1st floor','The main home with its covered deck and main entrance, plus the covered walkway to the detached 3-car garage, RV bay and woodworking shop / hobby / flex space.'),
    ('floorplan-2','2nd floor','The primary suite, office, bedroom and bath, joined by the catwalk overlooking the living room.'),
    ('floorplan-3','Barn','The monitor-style barn: three stalls, seven Dutch doors, a double-door entrance, room and bathroom.')],9):
    title('Floor plan',heading)
    photo(key,24,192,W-48,452,True)
    c.setFillColor(PALE);c.rect(M,75,CW,106,fill=1,stroke=0)
    text(summary,M+17,162,CW-34,size=10.5)
    text('Approximate living area: 3,018 sq ft per the listing. Floor-plan measurements are approximate.',M+17,108,CW-34,size=8.5,color=MUTED)
    footer(n)
# 12
c.setFillColor(BLUE);c.rect(0,0,W,H,fill=1,stroke=0)
photo('hero',0,471,W,321)
photo('logo-reverse',M,380,290,59,True)
text('9055 SE Eola Hills Rd\nAmity, Oregon',M,347,size=25,font='Semi',color=white)
text('RMLS #644545554',M,272,size=11,color=white)
for x,name,role,phone,email,license in [(M,'Linda Lindsay','Broker & Realtor','(503) 720-1805','linda.lindsay@exprealty.com','931000172'),(322,'Anita Borgaes','Principal Broker & Realtor','(503) 550-8050','anita@wcpcompanies.com','200211111')]:
    text(name,x,228,248,size=16,font='Semi',color=white)
    text(role,x,197,248,size=9,color=white)
    text(phone+'\n'+email+'\nLicense #'+license,x,175,248,size=9,color=white)
text('9055eolahills.com',M,91,size=16,font='Semi',color=white)
c.linkURL('https://9055eolahills.com/',(M,65,300,95),relative=0)
footer(12,True)
c.save()
print(pdf)
print('12-page Letter portrait booklet. Confirm printer trim/bleed before commercial printing.')
