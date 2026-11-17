"""Build the deployable module without development files.

    python tools/package.py

Creates:
  build/wildenmanager/              ready to upload by FTP to modules/wildenmanager/
  dist/wildenmanager-<version>.zip  installable from the PrestaShop module manager

Only runtime files are included: no tests, tools, docs, CI files or Git data.
"""
import re
import shutil
import zipfile
from pathlib import Path

MODULE = "wildenmanager"
ROOT = Path(__file__).resolve().parents[1]
RUNTIME_FILES = ["wildenmanager.php", "index.php", "logo.png", "LICENSE.md"]
RUNTIME_DIRS = ["classes", "controllers", "sql", "translations", "upgrade", "views"]


def version():
    match = re.search(r"const VERSION = '([^']+)'", (ROOT / "wildenmanager.php").read_text(encoding="utf-8"))
    if not match:
        raise SystemExit("VERSION constant not found in wildenmanager.php")
    return match.group(1)


def main():
    build = ROOT / "build" / MODULE
    build.mkdir(parents=True, exist_ok=True)
    wanted = set()
    for name in RUNTIME_FILES:
        wanted.add(Path(name))
    for name in RUNTIME_DIRS:
        wanted.update(p.relative_to(ROOT) for p in (ROOT / name).rglob("*") if p.is_file())

    # Overwrite in place instead of deleting the folder: on Windows, OneDrive or an FTP
    # client often keeps a lock on build/ subfolders, which makes rmtree fail.
    for path in sorted(build.rglob("*"), reverse=True):
        rel = path.relative_to(build)
        if path.is_file() and rel not in wanted:
            path.unlink()
        elif path.is_dir() and not any(path.iterdir()):
            try:
                path.rmdir()
            except OSError:
                pass
    for rel in sorted(wanted):
        target = build / rel
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(ROOT / rel, target)

    missing = [str(d.relative_to(build)) for d in [build, *build.rglob("*")] if d.is_dir() and not (d / "index.php").exists()]
    if missing:
        raise SystemExit("Directories without index.php: " + ", ".join(missing))

    dist = ROOT / "dist"
    dist.mkdir(exist_ok=True)
    archive = dist / f"{MODULE}-{version()}.zip"
    with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED) as zf:
        for path in sorted(build.rglob("*")):
            if path.is_file():
                zf.write(path, Path(MODULE) / path.relative_to(build))

    files = sum(1 for p in build.rglob("*") if p.is_file())
    print(f"{files} files -> {build.relative_to(ROOT)} and {archive.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
