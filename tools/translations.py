"""Check (and optionally extend) the Spanish translation catalogue.

    python tools/translations.py           # fails if a text used in the code is missing
    python tools/translations.py --add F   # adds the "source<TAB>target" lines of file F

Texts are collected from $this->trans('...', array(), 'Modules.Wildenmanager.Admin') in PHP,
{l s='...' d='Modules.Wildenmanager.Admin'} in templates, and the exception messages listed
in SERVICE_MESSAGES (translated at runtime by the admin controller).
"""
import hashlib
import re
import sys
from pathlib import Path
from xml.etree import ElementTree as ET
from xml.sax.saxutils import escape

ROOT = Path(__file__).resolve().parents[1]
DOMAIN = "Modules.Wildenmanager.Admin"
XLF = ROOT / "translations" / "es-ES" / "ModulesWildenmanagerAdmin.es-ES.xlf"
NS = {"x": "urn:oasis:names:tc:xliff:document:1.2"}
STR = r"'((?:[^'\\]|\\.)*)'"

# Exception messages thrown by services and shown to the employee through the translator.
SERVICE_FILES = ["classes/WmOrderHistoryService.php", "classes/WmOrderDeletionService.php"]


def unescape(value):
    return value.replace("\\'", "'").replace("\\\\", "\\")


def code_texts():
    texts = []
    for php in [ROOT / "wildenmanager.php", *sorted((ROOT / "controllers").rglob("*.php"))]:
        for m in re.finditer(r"->trans\(\s*" + STR + r",\s*array\(\),\s*'" + re.escape(DOMAIN) + "'", php.read_text(encoding="utf-8")):
            texts.append(unescape(m.group(1)))
    for tpl in sorted((ROOT / "views" / "templates").rglob("*.tpl")):
        for m in re.finditer(r"\{l s=" + STR + r" d='" + re.escape(DOMAIN) + "'", tpl.read_text(encoding="utf-8")):
            texts.append(unescape(m.group(1)))
    for name in SERVICE_FILES:
        for m in re.finditer(r"new RuntimeException\(\s*" + STR + r"\s*\)", (ROOT / name).read_text(encoding="utf-8")):
            texts.append(unescape(m.group(1)))
    seen = []
    for text in texts:
        if text not in seen:
            seen.append(text)
    return seen


def catalogue():
    tree = ET.parse(XLF)
    return {u.find("x:source", NS).text: u.find("x:target", NS).text for u in tree.iter("{%s}trans-unit" % NS["x"])}


def add(path):
    existing = catalogue()
    lines = [l for l in Path(path).read_text(encoding="utf-8").splitlines() if l.strip()]
    units = []
    for line in lines:
        source, target = line.split("\t")
        if source in existing:
            continue
        if re.findall(r"%[ds]", source) != re.findall(r"%[ds]", target):
            raise SystemExit("Placeholder mismatch: " + source)
        uid = hashlib.md5(source.encode("utf-8")).hexdigest()
        units.append('      <trans-unit id="%s">\n        <source>%s</source>\n        <target>%s</target>\n      </trans-unit>'
                     % (uid, escape(source), escape(target)))
    if units:
        content = XLF.read_text(encoding="utf-8")
        content = content.replace("    </body>", "\n".join(units) + "\n    </body>", 1)
        XLF.write_text(content, encoding="utf-8", newline="\n")
    print("added", len(units))


def check():
    existing = catalogue()
    missing = [t for t in code_texts() if t not in existing]
    if missing:
        print("Missing Spanish translations:")
        for text in missing:
            print("  " + text)
        raise SystemExit(1)
    print("All %d texts are translated." % len(code_texts()))


if __name__ == "__main__":
    if len(sys.argv) == 3 and sys.argv[1] == "--add":
        add(sys.argv[2])
    else:
        check()
