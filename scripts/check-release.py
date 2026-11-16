#!/usr/bin/env python3
"""Check a release ZIP for corruption and obvious private runtime data."""
import argparse
import re
import stat
from pathlib import PurePosixPath
import zipfile


def unsafe_member(info):
    name = info.filename
    path = PurePosixPath(name)
    parts = [part.lower() for part in path.parts]
    if not parts:
        return True
    base = parts[-1]
    file_type = (info.external_attr >> 16) & 0o170000
    if (path.is_absolute() or '..' in parts or '\\' in name or ':' in name
            or name.rstrip('/') != path.as_posix()
            or file_type not in (0, stat.S_IFREG, stat.S_IFDIR)):
        return True
    if any(part in {'vendor', 'node_modules', '.git', '.venv-loadtest', '__pycache__', 'backups'}
           or part.endswith('-backups') for part in parts):
        return True
    if any(part.startswith('.env') and (index != len(parts) - 1
           or part not in {'.env.example', '.env.production.example'})
           for index, part in enumerate(parts)):
        return True
    if info.is_dir():
        return False
    if (base in {'.phpunit.result.cache', 'auth.json', 'fixtures.json'}
            or base.endswith(('.log', '.pyc', '.pem', '.key', '.p12', '.db', '.sql'))
            or '.sqlite' in base or '.backup' in base or base.endswith('.before-phase3-hotfix')):
        return True
    if base != '.gitignore' and 'storage' in parts:
        return True
    if 'bootstrap' in parts and 'cache' in parts and base != '.gitignore':
        return True
    return 'tests' in parts and 'results' in parts


def audit_zip(archive):
    with zipfile.ZipFile(archive) as bundle:
        bad = [item.filename for item in bundle.infolist() if unsafe_member(item)]
        duplicates = len(set(bundle.namelist())) != len(bundle.namelist())
        corrupted = bundle.testzip()
        return {
            'status': 'pass' if not bad and not duplicates and corrupted is None else 'fail',
            'archive': str(archive), 'corrupt_member': corrupted, 'duplicates': duplicates,
            'unsafe_entries': bad, 'entry_count': len(bundle.infolist()),
        }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('archives', nargs='+')
    args = parser.parse_args()
    passed = True
    for archive in args.archives:
        result = audit_zip(archive)
        if result['status'] == 'pass':
            print(f"PASS: {archive} — {result['entry_count']} entries, valid CRCs, no private runtime paths.")
        else:
            print(f"FAIL: {archive}: {result}")
            passed = False
    return 0 if passed else 1


if __name__ == '__main__':
    raise SystemExit(main())
