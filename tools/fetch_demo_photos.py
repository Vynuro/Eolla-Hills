"""Download temporary local-preview photographs; never represent these as listing photos."""
from pathlib import Path
from urllib.request import urlopen
from concurrent.futures import ThreadPoolExecutor
import json
root=Path(__file__).resolve().parents[1]/'wordpress/wp-content/plugins/eola-hills/assets/photos'
root.mkdir(parents=True,exist_ok=True)
photos={
 'hero':('photo-1768067437750-f18d26d3397b','https://unsplash.com/photos/QirscBOAMII','Lumin Osity'),
 'living':('photo-1600210492486-724fe5c67fb0','https://images.unsplash.com/photo-1600210492486-724fe5c67fb0','Unsplash'),
 'kitchen':('photo-1556912172-45b7abe8b7e1','https://images.unsplash.com/photo-1556912172-45b7abe8b7e1','Unsplash'),
 'bedroom':('photo-1611892440504-42a792e24d32','https://images.unsplash.com/photo-1611892440504-42a792e24d32','Unsplash'),
 'detail':('photo-1600607687939-ce8a6c25118c','https://images.unsplash.com/photo-1600607687939-ce8a6c25118c','Unsplash'),
 'terrace':('photo-1776757845307-94853bf3c705','https://unsplash.com/photos/jXypQnK1d8o','Annie Spratt'),
 'landscape':('photo-1761342528836-33b8ad7d474f','https://unsplash.com/photos/HWbcEH2CCEQ','Emma Swoboda'),
 'workshop':('photo-1759756480941-7230dedf5fc9','https://unsplash.com/photos/Dzd-Xt3QWKw','Kristaps Grundsteins'),
 'barn':('photo-1580429438518-93e112682242','https://unsplash.com/photos/synTNyBP4wk','Ben Ashby'),
}
def fetch(entry):
 name,(photo,page,author)=entry
 url=f'https://images.unsplash.com/{photo}?auto=format&fit=crop&w=1800&q=85&fm=webp'
 target=root/f'{name}.webp'
 if not target.exists(): target.write_bytes(urlopen(url,timeout=45).read())
 return {'file':target.name,'source':page,'image_url':url,'credit':author,'use':'Temporary illustrative local preview; not the listed property.'}
with ThreadPoolExecutor(max_workers=4) as pool: records=list(pool.map(fetch,photos.items()))
(root/'credits.json').write_text(json.dumps(records,indent=2)+'\n',encoding='utf-8')
print(f'Downloaded {len(records)} demonstration photographs.')
