# Locust: 50-user load lab

Use a **disposable development copy** with PHP CLI, PDO SQLite, Python 3.10+ and the locked Composer dependencies. This is separate from the deterministic Node.js HTTP workload in [tests/Load/README.md](tests/Load/README.md).

    bash scripts/loadtest/setup.sh

The setup creates .env.loadtest and database/loadtest.sqlite if absent. It verifies the active driver and file before migrating. It uses a separate key, a random password for synthetic users, in-memory mail and a synchronous queue. Setup preserves test bookings when rerun and rotates the synthetic password. If an older .env.loadtest exists, review it first or move it out of the copy and rerun setup to generate a new key.

Terminal 1 (Linux, only loopback):

    PHP_CLI_SERVER_WORKERS=8 APP_ENV=loadtest php artisan serve --env=loadtest --no-reload --host=127.0.0.1 --port=8001

Terminal 2:

    python3 -m venv .venv-loadtest
    source .venv-loadtest/bin/activate
    python -m pip install -r tests/load/requirements.txt
    bash scripts/loadtest/run.sh

Start with an empty loadtest.sqlite for a fresh measurement; repeat runs retain the previous test bookings and may hit their slots. Move the disposable database and any journal files aside with the local test server stopped, then rerun setup.

The runner checks the sandbox handshake before sending any user traffic. It ramps to 25 booking staff, 20 browsing staff and 5 administrators. The default run is three minutes; bash scripts/loadtest/run.sh 30s is a smoke run. CSVs are written to storage/app/loadtest/last_*.csv. The synthetic administrator email is loadtest-admin-01@example.invalid; use the generated password in the private storage/app/loadtest/fixtures.json file if you want to inspect Admin → Load Test Lab. Never copy that file into a release.

Expected slot conflicts should be recorded as successful rejections. Treat HTTP 5xx, failed sign-ins or inconsistent booking state as failures. Keep this workload on the local sandbox. Repeat capacity testing on an isolated staging server matching the intended production stack.
