"""Retain open-license Google Fonts locally; the website makes no font API requests."""
from pathlib import Path
from urllib.request import urlopen
root=Path(__file__).resolve().parents[1]/'wordpress/wp-content/plugins/eola-hills/assets/fonts'
root.mkdir(parents=True,exist_ok=True)
files={
 'montserrat.ttf':'https://raw.githubusercontent.com/google/fonts/main/ofl/montserrat/Montserrat%5Bwght%5D.ttf',
 'montserrat-OFL.txt':'https://raw.githubusercontent.com/google/fonts/main/ofl/montserrat/OFL.txt',
}
for name,url in files.items():
    target=root/name
    if not target.exists(): target.write_bytes(urlopen(url,timeout=30).read())
    print(f'{name}: {target.stat().st_size} bytes')
