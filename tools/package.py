"""Build five individual packages and the cumulative Joomla package."""
from pathlib import Path
from datetime import date
import hashlib
import re
import xml.etree.ElementTree as ET
from zipfile import ZipFile, ZIP_DEFLATED

ROOT = Path(__file__).resolve().parents[1]
DIST = ROOT / "dist"
DIST.mkdir(exist_ok=True)
version = (ROOT / "VERSION").read_text().strip()
assert re.fullmatch(r"\d+\.\d+\.\d+", version)
release_date = (ROOT / 'RELEASE_DATE').read_text().strip()
assert date.fromisoformat(release_date).isoformat() == release_date
packages = {"lib_operaplus": "operaplus.xml",
            "mod_operaplus_atlas": "mod_operaplus_atlas.xml",
            "plg_fields_operaplusatlas": "operaplusatlas.xml",
            "mod_operaplus_cta": "mod_operaplus_cta.xml",
            "plg_editorsxtd_operaplus_templates": "operaplus_templates.xml"}
for name, manifest in packages.items():
    folder = ROOT / name
    tree = ET.parse(folder / manifest).getroot()
    assert tree.tag == "extension"
    for item in tree.findall("./files/*") + tree.findall("./languages/language"):
        assert (folder / item.text.strip()).exists(), (name, item.text)
    for media in tree.findall("./media"):
        for item in media:
            assert (folder / media.attrib["folder"] / item.text.strip()).exists(), (name, item.text)
    assert tree.findtext("version") == version, name
    assert tree.findtext("creationDate") == release_date, name
    with ZipFile(DIST / f"{name}-{version}.zip", "w", ZIP_DEFLATED) as archive:
        for file in sorted(folder.rglob("*")):
            if file.is_file():
                archive.write(file, file.relative_to(folder))
    print(name, version)

manifest = ROOT / "pkg_operaplus" / "pkg_operaplus.xml"
package = ET.parse(manifest).getroot()
assert package.attrib["type"] == "package"
assert package.findtext("version") == version
assert package.findtext("creationDate") == release_date
expected = {f"{name}-{version}.zip" for name in packages}
assert {f.text.strip() for f in package.findall("./files/file")} == expected
filename = f"pkg_operaplus-{version}.zip"
with ZipFile(DIST / filename, "w", ZIP_DEFLATED) as archive:
    archive.write(manifest, "pkg_operaplus.xml")
    for item in package.findall("./files/file"):
        archive.write(DIST / item.text.strip(), "packages/" + item.text.strip())

# Generated feed is published only with a stable GitHub release.
updates = ET.Element("updates")
update = ET.SubElement(updates, "update")
for key, value in {"name":"Opera+ Extensions", "description":"Opera+ editorial extensions for Joomla 6",
                   "element":"pkg_operaplus", "type":"package", "client":"site", "version":version,
                   "sha256":hashlib.sha256((DIST / filename).read_bytes()).hexdigest()}.items():
    ET.SubElement(update, key).text = value
download = ET.SubElement(ET.SubElement(update,"downloads"),"downloadurl",{"type":"full","format":"zip"})
download.text = f"https://github.com/jeckodevelopment/operaplus-extensions/releases/download/v{version}/{filename}"
ET.SubElement(update,"targetplatform",{"name":"joomla","version":r"6\..*"})
ET.SubElement(update,"php_minimum").text = "8.3.0"
ET.SubElement(ET.SubElement(update,"tags"),"tag").text = "alpha" if version.startswith("0.") else "stable"
ET.indent(updates)
ET.ElementTree(updates).write(DIST / "updates.xml",encoding="utf-8",xml_declaration=True)
print("pkg_operaplus", version)
