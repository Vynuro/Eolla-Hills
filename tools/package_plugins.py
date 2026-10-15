"""Package the Elementor theme and the single self-updating GitHub updater deterministically."""
from pathlib import Path
import hashlib, json, re, subprocess, zipfile
ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'artifacts'; OUT.mkdir(exist_ok=True)
THEME=ROOT/'wordpress/wp-content/themes/eola-hills'
UPDATER=ROOT/'wordpress/wp-content/plugins/eola-hills-updates'
def version(path):
    return re.search(r'^\s*\*?\s*Version:\s*(\d+\.\d+\.\d+)\s*$',path.read_text(encoding='utf-8'),re.M).group(1)
def package(source,name):
    target=OUT/name
    with zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED,compresslevel=9) as archive:
        for file in sorted(source.rglob('*')):
            if not file.is_file(): continue
            assert file.suffix in {'.php','.css','.js','.json','.svg','.txt','.md','.ttf','.webp','.png','.pdf'},file
            info=zipfile.ZipInfo(f'{source.name}/{file.relative_to(source).as_posix()}',date_time=(2026,1,1,0,0,0))
            info.compress_type=zipfile.ZIP_DEFLATED;info.external_attr=0o100644<<16
            archive.writestr(info,file.read_bytes())
    with zipfile.ZipFile(target) as archive: assert archive.testzip() is None
    assert target.stat().st_size <= 64*1024*1024,'Package exceeds updater download limit'
    print(target);return target
theme_version=version(THEME/'style.css')
assert f"EOLA_HILLS_VERSION', '{theme_version}'" in (THEME/'functions.php').read_text(encoding='utf-8')
theme_zip=package(THEME,f'eola-hills-theme-{theme_version}.zip')
updater_version=version(UPDATER/'eola-hills-updates.php')
updater_zip=package(UPDATER,f'eola-hills-updates-{updater_version}.zip')
manifest={'theme':'eola-hills','version':theme_version,'requires_wp':'6.8','requires_php':'8.0','package_asset':theme_zip.name,'sha256':hashlib.sha256(theme_zip.read_bytes()).hexdigest(),'commit':subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()}
# Updater 2.2+ installs a newer listed updater before the theme; older updaters ignore this key.
manifest['updater']={'version':updater_version,'package_asset':updater_zip.name,'sha256':hashlib.sha256(updater_zip.read_bytes()).hexdigest()}
(OUT/'eola-hills-theme-update.json').write_text(json.dumps(manifest,indent=2)+'\n',encoding='utf-8')
template=json.loads((THEME/'templates/property.json').read_text(encoding='utf-8'));ids=set()
def verify(elements):
    for el in elements:
        assert el['id'] not in ids,'Duplicate Elementor ID';ids.add(el['id'])
        assert el['elType'] in {'container','widget'}
        if el['elType']=='widget': assert el['widgetType'] in {'heading','text-editor','button','image','image-carousel','icon','accordion'}
        verify(el['elements'])
verify(template['content'])
for item in json.loads((THEME/'assets/media/manifest.json').read_text(encoding='utf-8')).values():
    assert hashlib.sha256((THEME/'assets/media'/item['file']).read_bytes()).hexdigest()==item['sha256']
    for size in item.get('sizes',{}).values():
        assert hashlib.sha256((THEME/'assets/media'/size['file']).read_bytes()).hexdigest()==size['sha256']
print(f'Checked {len(ids)} native elements, client media integrity and theme release manifest.')
