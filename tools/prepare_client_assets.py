"""Prepare only reviewed listing assets; leave client originals untouched."""
from pathlib import Path
from PIL import Image, ImageOps
import hashlib, json, shutil

ROOT=Path(__file__).resolve().parents[1]
SOURCE=ROOT/'From client'
DELIVERY=SOURCE/'9055-SE-Eola-Hills-Rd[3600]'
PHOTOS=DELIVERY/'9055-SE-Eola-Hills-Rd'
BRAND=DELIVERY/'Wine Country Properties Group'
OUT=ROOT/'wordpress/wp-content/plugins/eola-hills/assets/media'
OUT.mkdir(exist_ok=True)
manifest={}

def add(key,source,title,width=2200,lossless=False,description='Client-supplied photography of 9055 SE Eola Hills Rd. Photography by Mouve Media.'):
    im=ImageOps.exif_transpose(Image.open(source))
    im.thumbnail((width,width),Image.Resampling.LANCZOS)
    file=key+'.webp'
    im.save(OUT/file,'WEBP',quality=85,method=6,lossless=lossless)
    manifest[key]={'file':file,'title':title,'alt':title,'description':description,'source':source.relative_to(SOURCE).as_posix(),'sha256':hashlib.sha256((OUT/file).read_bytes()).hexdigest()}

# All selections are the supplied unstaged photographs. VS variants are not used.
selections={
 'hero':(3,'Front exterior and circular drive'),
 'living':(12,'Two-story living room with stone fireplace and upper catwalk'),
 'kitchen':(26,'Kitchen island, cabinetry and window wall'),
 'bedroom':(51,'Primary bedroom with views and en suite bath'),
 'detail':(38,'Upper catwalk and architectural interior detail'),
 'terrace':(68,'Covered rear deck and pergola'),
 'landscape':(80,'Aerial view of the home and 4.89-acre setting'),
 'barn':(65,'Monitor-style barn exterior'),
 'workshop':(78,'Detached heated workshop with custom counters and cabinetry'),
 'grounds':(70,'Landscaped grounds and natural rock features'),
 'acreage':(66,'Pasture and usable acreage'),
 'aerial':(79,'Overhead property aerial'),
 'garage':(76,'Detached garage and two-story RV bay'),
 'garage-interior':(75,'Oversized detached garage interior'),
 'primary-bath':(52,'Primary en suite bath with Jacuzzi tub'),
 'family-room':(35,'Family room and den with fireplace and custom millwork'),
 'office':(43,'Dedicated office with built-in desk and cabinetry'),
 'barn-interior':(62,'Barn interior, stalls and covered aisles'),
}
for key,(number,title) in selections.items():
    source=next(PHOTOS.glob(f'{number:02d}-*.jpg'))
    add(key,source,title)
add('logo',BRAND/'wcpg-exp-logos/web/wcpg-logo-exp-horizontal-transparent-960-web.png','Wine Country Properties Group | eXp Realty',lossless=True,description='Client-supplied combined brokerage logo.')
add('logo-reverse',BRAND/'wcpg-exp-logos/web/wcpg-logo-exp-horizontal-reverse-transparent-960-web.png','Wine Country Properties Group | eXp Realty',lossless=True,description='Client-supplied reversed brokerage logo.')
add('linda',SOURCE/'WhatsApp Image 2026-10-13 at 11.08.50 PM.jpeg','Linda Lindsay, Broker & Realtor',width=800,description='Client-supplied Linda Lindsay headshot; identity confirmed by the user.')
add('anita-contact',SOURCE/'Anita-Borgaes-Wine-Country-Properties (2).png','Anita Borgaes, Principal Broker & Realtor',lossless=True,description='Client-supplied Anita Borgaes contact card.')
# Keep every floor-plan PNG byte-for-byte unchanged, including its printed totals.
for i,source in enumerate(sorted((DELIVERY/'_floorplan').glob('*.png')),1):
    key=f'floorplan-{i}';file=key+'.png';shutil.copyfile(source,OUT/file)
    manifest[key]={'file':file,'title':f'Supplied floor plan {i:02d}','alt':f'Original supplied floor plan {i:02d} with dimensions','description':'Original client-supplied floor plan. Listing living area is 3,018 sq ft; printed plan totals differ.','source':source.relative_to(SOURCE).as_posix(),'sha256':hashlib.sha256((OUT/file).read_bytes()).hexdigest()}
source=SOURCE/'Eola Hills_Unbranded Amenities_Edit 10-8-26.pdf';file='property-amenities.pdf';shutil.copyfile(source,OUT/file)
manifest['amenities']={'file':file,'title':'9055 SE Eola Hills Rd - Amenities','alt':'','description':'Client-supplied amenities sheet dated October 8, 2026.','source':source.name,'sha256':hashlib.sha256((OUT/file).read_bytes()).hexdigest()}
(OUT/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf-8')
print(f'Prepared {len(manifest)} reviewed client assets; total {sum(p.stat().st_size for p in OUT.iterdir())/1024/1024:.1f} MB.')
