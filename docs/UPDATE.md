# Update an existing IPG Facility installation

The patch targets the source in **ipg-facility(3).zip**. It checks the entire managed source tree before writing. Keep it separate from the project and run it as the account that owns the source. Use Python 3.9+ on Linux.

## Preview

Verify the ZIP digest from the release handoff. Extract the patch outside the project. From its extracted directory:

    sha256sum -c SHA256SUMS
    python3 update.py install --target /srv/ipg-facility --dry-run

Review ADDED, MODIFIED and REMOVED entries in manifest.json. A mismatch names the conflicting file and expected hash. Stop and compare your local changes to the original or merge them in a separate copy. Do not force the patch over edits. Generated dependency folders, environment files, local databases, uploads, logs, caches and old backup folders are outside the patch's scope. Extra files in managed source directories are rejected, except documented runtime outputs. Running the installer twice verifies the finalized tree and skips writing another backup.

## Install

1. Rehearse on a copy. Take a tested private backup of your database, .env and persistent files. Record the current APP_KEY and PHP/DB versions privately.
2. Schedule maintenance. Take the app down (php artisan down --retry=60), stop its queue service, suspend its scheduler entry and pause any other writes. Back up after writes stop if your snapshot must represent that exact point. Ensure no other process edits the source while installing.
3. Run the patch and retain its printed backup path:

       python3 update.py install --target /srv/ipg-facility --backup-dir /var/backups/ipg-facility/source

   The installer validates payloads and baseline, backs up every modified or removed file before writing, and verifies the resulting source. It does not execute PHP, edit .env, change dependencies, update caches or touch the database.
4. From the Laravel root, keep your existing .env and key. Check production settings against .env.production.example and apply only the needed changes. Then run:

       php artisan config:clear
       php artisan route:clear
       php artisan view:clear
       composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
       composer check-platform-reqs --no-dev
       php artisan migrate:status

   This release adds no migrations. If an older installation has pending migrations, back it up and test those migrations against a clone first. Run php artisan migrate --force --no-interaction only when ready to apply them. Do not reseed or regenerate the application key.
5. Run php artisan optimize. Reload PHP-FPM if it uses a persistent OPcache configuration. Start the worker with the updated code, restore cron and run php artisan up after you have verified the release. Follow the health, permissions and role checks in [DEPLOYMENT.md](DEPLOYMENT.md).

## Roll back the source

Keep the application in maintenance with workers and scheduler paused. Use the exact source backup path printed by install:

    python3 update.py rollback --target /srv/ipg-facility --backup /var/backups/ipg-facility/source/ipg-TIMESTAMP-ID --dry-run
    python3 update.py rollback --target /srv/ipg-facility --backup /var/backups/ipg-facility/source/ipg-TIMESTAMP-ID

Rollback verifies backup and current source hashes, restores the original affected files and removes only unchanged files added by this update. If source was edited after installation, it stops with the conflicting paths. Preserve any edits before resolving a conflict. A partial install can be rolled back when the affected files still match their expected before or after hashes. Empty directories may remain.

After rollback, install dependencies from the restored composer.lock and rebuild config, routes and views. If you separately ran migrations, verify the restored code against the current schema. The tool does not reverse migrations. For an incompatible schema, decide with your DBA whether to restore the matching snapshot or apply a forward repair; preserve any intervening writes. Recheck health, restart the worker and scheduler and reopen the application.
