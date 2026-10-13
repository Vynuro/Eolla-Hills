"""Make a private prior-version theme to test fresh install, then real GitHub upgrade."""
from pathlib import Path
import hashlib,json,zipfile
r=Path(__file__).resolve().parents[1];out=r/'artifacts'
m=json.loads((out/'eola-hills-theme-update.json').read_text());current=m['version'];version='1.9.9'
target=out/'eola-hills-bootstrap-test.zip'
with zipfile.ZipFile(out/m['package_asset']) as src,zipfile.ZipFile(target,'w',zipfile.ZIP_DEFLATED) as dst:
    for info in src.infolist():
        data=src.read(info.filename)
        if info.filename in ('eola-hills/style.css','eola-hills/functions.php'):
            data=data.replace(f'Version: {current}'.encode(),f'Version: {version}'.encode()).replace(f"VERSION', '{current}'".encode(),f"VERSION', '{version}'".encode())
        dst.writestr(info,data)
m.update(version=version,package_asset=f'eola-hills-theme-{version}.zip',fixture_file=target.name,sha256=hashlib.sha256(target.read_bytes()).hexdigest(),asset_url='https://api.github.com/repos/malikazhar545/5eolahills/releases/assets/999997')
(out/'bootstrap-test.json').write_text(json.dumps(m));print('Fresh-install fixture ready.')
