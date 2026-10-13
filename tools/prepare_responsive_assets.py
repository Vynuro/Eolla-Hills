"""Prebuild responsive photo sizes so hosting never resizes 27 assets during sync."""
from pathlib import Path
from PIL import Image
import hashlib,json
root=Path(__file__).resolve().parents[1]/'wordpress/wp-content/themes/eola-hills/assets/media'
manifest=json.loads((root/'manifest.json').read_text(encoding='utf-8'))
(root/'responsive').mkdir(exist_ok=True)
for key,item in manifest.items():
    if not item['file'].endswith(('.webp','.png')):continue
    with Image.open(root/item['file']) as image:
        item.update(width=image.width,height=image.height,sizes={})
        if key.startswith('floorplan-') or key in ('logo','logo-reverse','linda','anita-contact'):continue
        for width in (768,1536):
            if width>=image.width:continue
            copy=image.copy();copy.thumbnail((width,width),Image.Resampling.LANCZOS)
            file=f'responsive/{key}-{width}.webp';copy.save(root/file,'WEBP',quality=82,method=4)
            item['sizes'][f'eola-{width}']={'file':file,'width':copy.width,'height':copy.height,'mime-type':'image/webp','sha256':hashlib.sha256((root/file).read_bytes()).hexdigest()}
(root/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf-8')
print('Prepared responsive photos and dimensions; original plans/PDF untouched.')
