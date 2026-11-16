#!/usr/bin/env python3
"""Create an online, transactionally consistent SQLite snapshot using SQLite backup API.
Does not alter the live DB. Intended for trusted project administrators only.
"""
import argparse
import datetime
import os
from pathlib import Path
import sqlite3
import sys


def main():
    parser = argparse.ArgumentParser(description='Back up the local IPG SQLite database.')
    parser.add_argument('--root', default='.', help='Laravel application root (artisan directory)')
    parser.add_argument('--database', default='database/database.sqlite', help='SQLite path, relative to root')
    parser.add_argument('--output-dir', default='storage/app/private/backups', help='Private output folder')
    args = parser.parse_args()
    root = Path(args.root).resolve()
    if not (root / 'artisan').is_file():
        parser.error('Project root has no artisan file')
    source = (root / args.database).resolve()
    if not source.is_file():
        parser.error('SQLite database not found as a file: ' + str(source))
    # Do not point this utility at another provider or a differently configured DB.
    if source.suffix != '.sqlite':
        parser.error('Expected a .sqlite database. Use a database-specific backup for MySQL/PostgreSQL.')
    output_dir = (root / args.output_dir).resolve()
    if source == output_dir or output_dir == source.parent:
        parser.error('Do not back up over the live database')
    if output_dir.is_relative_to((root / 'public').resolve()):
        parser.error('Choose a private output directory outside the web root')
    output_dir.mkdir(parents=True, exist_ok=True, mode=0o700)
    stamp = datetime.datetime.now().strftime('%Y%m%d-%H%M%S-%f')
    output = output_dir / f'ipg-facility-{stamp}.sqlite'
    # mode=ro prevents creating a blank DB if source path is wrong.
    os.close(os.open(output, os.O_CREAT | os.O_EXCL | os.O_WRONLY, 0o600))
    try:
        with sqlite3.connect(source.as_uri() + '?mode=ro', uri=True) as origin:
            if not origin.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='bookings'").fetchone():
                raise RuntimeError('Source database does not contain the bookings table')
            with sqlite3.connect(output) as snapshot:
                origin.backup(snapshot)
                status = snapshot.execute('PRAGMA quick_check').fetchone()[0]
                if status != 'ok':
                    raise RuntimeError('Backup failed integrity check: ' + status)
        os.chmod(output, 0o600)
        print('SUCCESS: Verified SQLite snapshot at:', output)
        print('Keep an encrypted copy outside the server. Restore only after checking the target database.')
    except Exception:
        output.unlink(missing_ok=True)
        raise


if __name__ == '__main__':
    try:
        main()
    except Exception as error:
        sys.exit('ERROR: ' + str(error))
