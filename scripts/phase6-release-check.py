#!/usr/bin/env python3
"""Read-only IPG release checks: SQLite integrity, load result, and ZIP hygiene.

Does not connect to production services or modify data. Does not print secrets.
"""
import argparse
import json
from pathlib import Path
import sqlite3
import sys
import runpy


def check_database(path: Path):
    if not path.is_file():
        return {"status": "not_found", "path": str(path)}
    uri = path.resolve().as_uri() + '?mode=ro'
    with sqlite3.connect(uri, uri=True, timeout=3) as db:
        integrity = db.execute('PRAGMA integrity_check').fetchall()
        foreign_keys = db.execute('PRAGMA foreign_key_check').fetchall()
        tables = {row[0] for row in db.execute("SELECT name FROM sqlite_master WHERE type='table'")}
        required = {'users', 'facilities', 'bookings', 'booking_events',
                    'notifications', 'booking_attention_reminders',
                    'facility_closures', 'recurring_booking_series'}
        migrations = db.execute('SELECT COUNT(*) FROM migrations').fetchone()[0] if 'migrations' in tables else 0
        result = {
            'path': str(path), 'status': 'pass' if integrity == [('ok',)] and not foreign_keys and required <= tables else 'fail',
            'integrity': integrity[0][0] if integrity else 'empty',
            'foreign_key_errors': len(foreign_keys),
            'missing_tables': sorted(required - tables), 'migrations': migrations,
        }
        return result


def check_load_summary(path: Path):
    data = json.loads(path.read_text(encoding='utf-8'))
    reasons = []
    def require(condition, message):
        if not condition:
            reasons.append(message)
    require(data.get('passed') is True, 'Load harness reported failure')
    users = data.get('users', {})
    require(users.get('total') == 50 and users.get('peak_in_flight_http') == 50,
            'Expected 50 simultaneous virtual sessions')
    require(data.get('unexpected_http_responses') == 0, 'Unexpected HTTP responses present')
    require(data.get('failed_checks') == 0, 'Load assertion failures present')
    require(data.get('protected_source_files_unchanged') is True, 'Protected sources were modified')
    require(data.get('integrity', {}).get('passed') is True, 'Load database verification did not pass')
    require(data.get('integrity', {}).get('overlaps') == [], 'Overlapping active bookings')
    require(data.get('integrity', {}).get('booking_race_winners') == 1, 'Booking race winner count differs from 1')
    require(data.get('integrity', {}).get('admin_race_decision_events') == 1, 'Admin race decision count differs from 1')
    require(data.get('budget', {}).get('passed') is True, 'Latency budget exceeded')
    return {'status': 'pass' if not reasons else 'fail', 'report': str(path),
            'date': data.get('generated_at'), 'requests': data.get('workload', {}).get('measured_requests'),
            'p95_ms': data.get('latency', {}).get('p95_ms'),
            'p99_ms': data.get('latency', {}).get('p99_ms'), 'reasons': reasons}


def check_zip(path: Path):
    checker = runpy.run_path(str(Path(__file__).with_name('check-release.py')))
    return checker['audit_zip'](path)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--root', default='.', help='Laravel project folder')
    parser.add_argument('--load-summary', type=Path, help='Recent tests/Load/results/.../summary.json')
    parser.add_argument('--archive', type=Path, help='Deployment or patch ZIP to screen for sensitive content')
    parser.add_argument('--output', type=Path, help='Optional local JSON report path')
    args = parser.parse_args()
    root = Path(args.root).resolve()
    if not (root / 'artisan').is_file():
        parser.error('--root must contain Laravel artisan')
    result = {'databases': [], 'load_summary': None, 'archive': None}
    for name in ['database.sqlite', 'loadtest.sqlite']:
        path = root / 'database' / name
        if path.is_file():
            result['databases'].append(check_database(path))
    if not result['databases']:
        print('WARNING: No SQLite files found; database integrity check skipped.', file=sys.stderr)
    if args.load_summary:
        result['load_summary'] = check_load_summary(args.load_summary)
    if args.archive:
        result['archive'] = check_zip(args.archive)
    statuses = [x['status'] for x in result['databases']]
    statuses += [v['status'] for v in (result['load_summary'], result['archive']) if v]
    result['passed'] = bool(statuses) and all(status == 'pass' for status in statuses)
    print(json.dumps(result, indent=2))
    if args.output:
        args.output.write_text(json.dumps(result, indent=2) + '\n', encoding='utf-8')
    return 0 if result['passed'] else 1


if __name__ == '__main__':
    sys.exit(main())
