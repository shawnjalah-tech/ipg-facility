#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
case "$#" in
    0) ;;
    1) [[ $1 == --demo ]] || { echo 'Usage: bash bin/setup.sh [--demo]' >&2; exit 2; } ;;
    *) echo 'Usage: bash bin/setup.sh [--demo]' >&2; exit 2 ;;
esac

if [[ ! -e .env ]]; then (umask 077; cp -n .env.example .env); fi
composer install --no-interaction --prefer-dist

# Read the same configuration Artisan will use. Do not create a database or
# generate a key for a different environment or SQLite path.
key_status=$(php -r '
    require "vendor/autoload.php";
    $app = require "bootstrap/app.php";
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $path = database_path("database.sqlite");
    if (! $app->environment("local") || $app->configurationIsCached()
        || config("database.default") !== "sqlite"
        || config("database.connections.sqlite.database") !== $path
        || config("database.connections.sqlite.url") || is_link($path)) {
        fwrite(STDERR, "Local setup requires uncached local config with database/database.sqlite. See docs/DEPLOYMENT.md for server setup.\n");
        exit(1);
    }
    echo config("app.key") ? "present" : "missing";
')
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
if [[ $key_status == missing ]]; then php artisan key:generate; fi
if [[ ! -e database/database.sqlite ]]; then (umask 077; touch database/database.sqlite); fi
php artisan migrate --no-interaction
if [[ ${1:-} == --demo ]]; then php artisan db:seed --no-interaction; fi
printf '\nCreate an administrator: php artisan app:create-admin\nRun locally: composer dev\n'
