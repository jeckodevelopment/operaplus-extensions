"""Build preview installable ZIPs with manifests at the root."""
from pathlib import Path
import xml.etree.ElementTree as ET
from zipfile import ZipFile, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / "dist"
DIST.mkdir(exist_ok=True)
packages = {"lib_operaplus": "operaplus.xml",
            "mod_operaplus_atlas": "mod_operaplus_atlas.xml",
            "plg_fields_operaplusatlas": "operaplusatlas.xml"}
for name, manifest in packages.items():
    folder = ROOT / name
    tree = ET.parse(folder / manifest).getroot()
    assert tree.tag == "extension"
    for item in tree.findall("./files/*") + tree.findall("./languages/language"):
        assert (folder / item.text.strip()).exists(), (name, item.text)
    version = tree.findtext("version")
    assert version == "0.1.0"
    with ZipFile(DIST / f"{name}-{version}.zip", "w", ZIP_DEFLATED) as archive:
        for file in sorted(folder.rglob("*")):
            if file.is_file():
                archive.write(file, file.relative_to(folder))
    print(name, version)
