#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/../.."
if [[ -e bootstrap/cache/config.php || -e bootstrap/cache/routes-v7.php ]]; then
    echo 'Use a disposable development copy with no config or route cache.' >&2
    exit 1
fi
python3 scripts/loadtest/prepare.py
# Verify the database before the first migration. Never touch the normal database.
APP_ENV=loadtest php artisan loadtest:seed --env=loadtest --verify-only
APP_ENV=loadtest php artisan migrate --env=loadtest --force --no-interaction
APP_ENV=loadtest php artisan loadtest:seed --env=loadtest
