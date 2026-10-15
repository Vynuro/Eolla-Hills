"""Isolated 8086 self-update fixtures: next theme/updater patch versions and fake GitHub release maps.

Run after package_plugins.py. Writes artifacts/selftest/ (ignored); never published.
"""
from pathlib import Path
import hashlib, json, shutil, zipfile

root = Path(__file__).resolve().parents[1]
here = root / 'artifacts/selftest'; here.mkdir(exist_ok=True)
base = json.loads((root / 'artifacts/eola-hills-theme-update.json').read_text())
for name in (base['package_asset'], base['updater']['package_asset']):
    shutil.copyfile(root / 'artifacts' / name, here / name)


def patch(version):
    parts = version.split('.'); parts[-1] = str(int(parts[-1]) + 1); return '.'.join(parts)


theme, updater = base['version'], base['updater']['version']
next_theme, next_updater = patch(theme), patch(updater)


def bump(src, dst, replace):
    with zipfile.ZipFile(here / src) as a, zipfile.ZipFile(here / dst, 'w', zipfile.ZIP_DEFLATED) as b:
        for info in a.infolist():
            data = a.read(info.filename)
            for name, pairs in replace.items():
                if info.filename == name:
                    for old, new in pairs:
                        assert old in data, (name, old)
                        data = data.replace(old, new)
            b.writestr(info, data)


def sha(name):
    return hashlib.sha256((here / name).read_bytes()).hexdigest()


def release(tag, manifest, files):
    (here / f'manifest-{tag}.json').write_text(json.dumps(manifest))
    assets = [{'id': 900 + i, 'name': n, 'file': f} for i, (n, f) in enumerate([('eola-hills-theme-update.json', f'manifest-{tag}.json')] + files)]
    (here / f'release-{tag}.json').write_text(json.dumps({'assets': assets}))


release('current', base, [(base['package_asset'], base['package_asset']), (base['updater']['package_asset'], base['updater']['package_asset'])])

t_zip, u_zip = f'eola-hills-theme-{next_theme}.zip', f'eola-hills-updates-{next_updater}.zip'
bump(base['package_asset'], t_zip, {
    'eola-hills/style.css': [(f'Version: {theme}'.encode(), f'Version: {next_theme}'.encode())],
    'eola-hills/functions.php': [(f"VERSION', '{theme}'".encode(), f"VERSION', '{next_theme}'".encode())],
    'eola-hills/templates/property.json': [(b'Skip to property', b'Skip to updated property')],
})
bump(base['updater']['package_asset'], u_zip, {
    'eola-hills-updates/eola-hills-updates.php': [(f'Version: {updater}'.encode(), f'Version: {next_updater}'.encode())],
    'eola-hills-updates/inc/class-updates.php': [(b"'Eola-Hills-Updates/2.0'", b"'Eola-Hills-Updates/self-update-test'")],
})
nxt = dict(base, version=next_theme, package_asset=t_zip, sha256=sha(t_zip),
           updater={'version': next_updater, 'package_asset': u_zip, 'sha256': sha(u_zip)})
release('next', nxt, [(t_zip, t_zip), (u_zip, u_zip)])
# Tampered updater: same manifest but altered bytes must be rejected by checksum.
bad = dict(nxt)
(here / 'tampered.zip').write_bytes((here / u_zip).read_bytes() + b'x')
release('tampered', bad, [(t_zip, t_zip), (u_zip, 'tampered.zip')])
print(f'Self-update fixtures ready: {theme}/{updater} -> {next_theme}/{next_updater} (not published).')
