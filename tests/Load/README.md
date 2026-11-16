# IPG Facility — 50-user functional and load tests

## Run

From the Laravel project root, with its existing Composer dependencies installed:

```bash
php artisan test --compact
node tests/Load/run.mjs
```

The load runner requires Node.js 20+ and PHP CLI with PDO SQLite and multi-worker server support. Run on Linux or WSL2; macOS uses a shared loopback IP and may take an extra minute to authenticate because the application's login limit remains active. Set `PHP_BINARY` if PHP is outside PATH:

```bash
PHP_BINARY=/path/to/php node tests/Load/run.mjs --duration=60 --workers=8 --history=500
```

No npm package, Composer package, migration of your existing database, or environment edit is required. All additional files are under `tests/`.

The default output is a new timestamped directory under `tests/Load/results/`. The runner writes `summary.md`, `summary.json`, and per-request `requests.csv`. It exits nonzero for unexpected responses, incorrect database state, failed race assertions, isolation changes, or a missed latency budget.

## Workload

Fifty separately authenticated HTTP sessions participate in synchronized phases, with one request stream per user. A phase starts all 50 together. Each mixed round has four phases; the test repeats complete rounds for at least 60 seconds. This is a closed workload with barriers and no think time, designed to create repeatable bursts. It is not 50 browser processes or an open-arrival maximum-capacity test.

| Users | Activity |
| --- | --- |
| 25 booking staff | Create a booking, find it through their own filtered list, inspect bookings, cancel pending requests or approved bookings. |
| 20 browsing staff | Read dashboards, availability, booking forms, and filtered booking lists; do not write bookings. |
| 5 administrators | Read the unified booking queue, approve/reject assigned requests, and open monthly reports or facility analytics. |

Each group of five booking staff produces one approved booking, one rejected booking, two cancelled bookings (one cancelled before approval and one after), and one pending booking per round. Thus every completed round must end with **5 approved, 5 rejected, 10 cancelled, and 5 pending** records, with the exact audit-event counts and correct owners. Generated time slots do not overlap.

The seed includes 500 historical bookings spread across pending, approved, rejected, cancelled, and expired states. Separate facilities keep seeded history from accidentally contaminating the race tests.

Two additional 50-user phases test concurrency:

1. **Slot race:** all 25 booking staff submit the same facility/time while the other 25 users browse. Exactly one submission must succeed; the other 24 must return an HTTP 422 with a conflict validation error. The database must contain exactly one winner.
2. **Admin race:** five administrators approve the same pending request while all 45 staff browse. Exactly one approval must succeed; four must return HTTP 422 with stale-status validation. The database must contain one additional decision event.

Real HTTP checks also verify staff denial from admin routes, denial of another user's booking/details/cancellation, rejection of a write without a CSRF token, existing booking CSV content, facility analytics CSV content, and a valid PDF report. Application login and CSRF middleware remain enabled. Linux assigns a distinct real loopback source IP to each user; it does not forge forwarded-IP headers. On other supported platforms, login warmup honors `Retry-After` when a shared IP reaches the 30/minute limit.

## Functional tests

`tests/Feature/FiftyUserWorkflowTest.php` adds three PHPUnit tests:

- All 50 account roles complete the mixed workflows, with explicit final counts, ownership boundaries, notification counts, and audit checks. These in-process functional requests are sequential; HTTP concurrency is tested by the separate load runner.
- A stale administrative correction cannot overwrite a newer approval or add another audit/notification entry.
- The per-IP login ceiling applies across distinct account names.

The existing suite also covers overlap/adjacency, all-day and overnight bookings, inactive facilities, invalid/overlong/past dates, cancellation after start, expiry, rescheduling approval/rejection/withdrawal, stale decisions, password reset, registration, protected history, search/pagination, and CSV formula escaping.

## Isolation

The runner starts a **new loopback-only PHP server** on an ephemeral port. `bootstrap.php` refuses to start without a matching private workspace/token marker. The Laravel `.env`, existing database, and application config/route caches are not loaded for this server.

The fixture's `migrate` call applies only to a new SQLite file inside a private operating-system temporary directory. Cache, sessions, compiled views, logs, package manifests, and app encryption keys are separate. Passwords are random for each run. Mail uses an in-memory array transport; queue jobs execute synchronously. No email is sent.

On success the disposable workspace is deleted. On failure it is retained for debugging, and its path is printed. It contains only synthetic accounts and records. The results contain no password, session cookie, CSRF token, encryption key, or `.env` contents. The runner checks that `.env`, the normal SQLite database, `composer.json`, and `composer.lock` remain unchanged.

## Options and interpretation

```bash
# Quick smoke run, one complete mixed round plus both races and security/export checks
node tests/Load/run.mjs --duration=0 --rounds=1 --history=100

# Longer repeatable run
node tests/Load/run.mjs --duration=300 --workers=8 --history=1000 --output=/path/to/results

# Explicit latency budgets in milliseconds
node tests/Load/run.mjs --p95=2000 --p99=5000

# Reproduce a specific PHP memory profile
node tests/Load/run.mjs --memory=128M
```

`--rounds=0` means no round cap. A positive round cap can shorten the requested duration. Set `--duration=0` to run exactly the requested number of rounds, without a time cap. Requests time out after 30 seconds; writes are never automatically retried. A round completes before duration is checked, so elapsed time can exceed the target. Login/session warmup is reported separately.

Latency measures each full HTTP response, including server queueing, measured with a monotonic clock. Reports provide p50/p95/p99/max, operation and role breakdowns, response bytes, HTTP statuses, throughput, observed serving processes, peak active users, and peak in-flight requests. The configured eight PHP workers may serve alongside the parent process; actual responding process IDs are counted. CSV/PDF requests are included in measured performance, while deliberate security rejections are excluded from the aggregate latency budget. Expected race conflicts are reported separately from unexpected errors.

**The default p95 ≤ 2,000 ms and p99 ≤ 5,000 ms are proposed test budgets, not an agreed production SLA.** Results depend on hardware, PHP process configuration, database, queue/mail settings, dataset, and request mix. This local PHP development-server/SQLite run verifies this scenario; it does not establish production capacity. Browser rendering, static asset downloads, real SMTP, an asynchronous queue worker, and production MySQL/PostgreSQL contention are outside this profile.

For the next stage, repeat the same assertions in a dedicated staging deployment configured like production, then run a longer soak and progressively increase concurrency. Keep expected conflict responses separate from faults throughout.

See ../../RELEASE_NOTES.md for results on the finalized release.
