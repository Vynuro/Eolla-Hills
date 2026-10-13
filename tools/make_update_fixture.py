"""Create an unpublished next-patch package only for isolated upgrade tests."""
from pathlib import Path
import hashlib,json,zipfile
root=Path(__file__).resolve().parents[1]
manifest=json.loads((root/'artifacts/eola-hills-update.json').read_text())
current=manifest['version']
parts=[int(p) for p in current.split('.')]
parts[-1]+=1
version='.'.join(map(str,parts))
source=root/'artifacts'/manifest['package_asset']
target=root/'artifacts'/f'eola-hills-test-{version}.zip'
with zipfile.ZipFile(source) as src, zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as dst:
    for info in src.infolist():
        data=src.read(info.filename)
        if info.filename=='eola-hills/eola-hills.php':
            data=data.replace(f'Version: {current}'.encode(),f'Version: {version}'.encode()).replace(f"VERSION', '{current}'".encode(),f"VERSION', '{version}'".encode())
        dst.writestr(info,data)
manifest.update(version=version,package_asset=f'eola-hills-{version}.zip',fixture_file=target.name,sha256=hashlib.sha256(target.read_bytes()).hexdigest(),asset_url='https://api.github.com/repos/malikazhar545/5eolahills/releases/assets/999999')
(root/'artifacts/update-test.json').write_text(json.dumps(manifest))
print(f'Created isolated-test fixture {version} (not a published release).')
