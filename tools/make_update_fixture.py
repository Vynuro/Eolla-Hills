"""Create an unpublished next-version package only for isolated upgrade tests."""
from pathlib import Path
import hashlib,json,zipfile
root=Path(__file__).resolve().parents[1]
source=root/'artifacts/eola-hills-1.0.0.zip'
target=root/'artifacts/eola-hills-test-1.0.1.zip'
with zipfile.ZipFile(source) as src, zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as dst:
    for info in src.infolist():
        data=src.read(info.filename)
        if info.filename=='eola-hills/eola-hills.php':
            data=data.replace(b'Version: 1.0.0',b'Version: 1.0.1').replace(b"VERSION', '1.0.0'",b"VERSION', '1.0.1'")
        dst.writestr(info,data)
manifest=json.loads((root/'artifacts/eola-hills-update.json').read_text())
manifest.update(version='1.0.1',package_asset='eola-hills-1.0.1.zip',sha256=hashlib.sha256(target.read_bytes()).hexdigest(),asset_url='https://api.github.com/repos/malikazhar545/5eolahills/releases/assets/999999')
(root/'artifacts/update-test.json').write_text(json.dumps(manifest))
print('Created isolated-test fixture (not a published release).')
