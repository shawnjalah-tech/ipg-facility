#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
if [[ ! -f .env.loadtest || ! -f database/loadtest.sqlite || ! -f storage/app/loadtest/fixtures.json ]]; then
    echo 'First run bash scripts/loadtest/setup.sh in a disposable development copy.' >&2
    exit 1
fi
if ! python3 -c 'import locust' 2>/dev/null; then
    echo 'Locust is not installed for python3. Activate your load-test venv first.' >&2
    exit 1
fi
run_time=${1:-3m}
if [[ $# -gt 1 || ! $run_time =~ ^[1-9][0-9]*[sm]$ ]]; then
    echo 'Usage: bash scripts/loadtest/run.sh [duration, e.g. 30s or 3m]' >&2
    exit 2
fi
mkdir -p storage/app/loadtest
export IPG_LOADTEST_FIXTURES="$PWD/storage/app/loadtest/fixtures.json"
python3 -m locust \
    -f tests/load/locustfile.py \
    --host http://127.0.0.1:8001 \
    --headless \
    --users 50 \
    --spawn-rate 10 \
    --run-time "$run_time" \
    --csv storage/app/loadtest/last \
    --csv-full-history
