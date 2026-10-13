"""Build deterministic installable ZIPs and the GitHub update manifest."""
from pathlib import Path
import hashlib
import json
import re
import subprocess
import zipfile

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'artifacts'
OUT.mkdir(exist_ok=True)
versions = {}
for slug in ('eola-hills', 'eola-hills-updates'):
    source = ROOT / 'wordpress/wp-content/plugins' / slug
    main = (source / f'{slug}.php').read_text(encoding='utf-8')
    version = re.search(r'^ \* Version: (\d+\.\d+\.\d+)$',main,re.M).group(1)
    versions[slug] = version
    target = OUT / f'{slug}-{version}.zip'
    with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
        for file in sorted(source.rglob('*')):
            if file.is_file():
                relative = file.relative_to(source)
                assert file.suffix in {'.php','.css','.js','.json','.svg','.txt','.md','.ttf','.webp'}, file
                info = zipfile.ZipInfo(f'{slug}/{relative.as_posix()}',date_time=(2026,1,1,0,0,0))
                info.compress_type=zipfile.ZIP_DEFLATED
                info.external_attr=0o100644 << 16
                archive.writestr(info,file.read_bytes())
    with zipfile.ZipFile(target) as archive:
        assert archive.testzip() is None
        assert f'{slug}/{slug}.php' in archive.namelist()
    print(target)

version=versions['eola-hills']
package=OUT/f'eola-hills-{version}.zip'
manifest={'slug':'eola-hills','plugin':'eola-hills/eola-hills.php','version':version,'requires_wp':'6.8','requires_php':'8.0','package_asset':package.name,'sha256':hashlib.sha256(package.read_bytes()).hexdigest(),'commit':subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()}
(OUT/'eola-hills-update.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf-8')

template=json.loads((ROOT/'wordpress/wp-content/plugins/eola-hills/templates/property.json').read_text(encoding='utf-8'))
ids=set()
def verify(elements):
    for el in elements:
        assert el['id'] not in ids, 'Duplicate Elementor ID'
        ids.add(el['id'])
        assert el['elType'] in {'container','widget'}
        if el['elType']=='widget':
            assert el['widgetType'] in {'heading','text-editor','button','image','image-carousel','icon'}, el['widgetType']
        verify(el['elements'])
verify(template['content'])
print(f'Checked {len(ids)} native Elementor elements; manifest SHA-256 generated.')
