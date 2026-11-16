# Install IPG Facility on a server

Use the complete production ZIP for a **new** installation. For an existing database and application, use [UPDATE.md](UPDATE.md). First rehearse the steps on a private staging host with the same PHP and database versions you plan to use.

## 1. Server and dependencies

Use a maintained Linux server with PHP **8.4.1–8.5.x** for both the CLI and PHP-FPM. Debian 13 with its PHP 8.4 packages is one option. On another distribution, use maintained packages from your approved repository. Install Composer 2 (runtime API 2.2 or later), Nginx or Apache with PHP-FPM, unzip, CA certificates and a cron service. Python 3.9+ is used by the update installer and SQLite backup helper.

For Debian 13 with Nginx and a local MariaDB server, for example:

    sudo apt update
    sudo apt install nginx mariadb-server cron ca-certificates unzip python3 \
        php8.4-cli php8.4-fpm php8.4-mbstring php8.4-xml php8.4-curl \
        php8.4-mysql php8.4-sqlite3 php8.4-zip php8.4-intl php8.4-gd

Install a maintained Composer 2 package or follow the [official Composer installer](https://getcomposer.org/download/) and verify its checksum. Use php -v and php -m for CLI; check the corresponding FPM configuration too. Laravel/locked libraries need ctype, DOM/XML/libxml, fileinfo, filter, hash, iconv, JSON, mbstring, OpenSSL, PCRE, PDO, session and tokenizer. Enable pdo_mysql for MySQL/MariaDB or pdo_sqlite and sqlite3 for SQLite. Enable pcntl for worker timeouts on Linux. Curl/ZIP help Composer; XMLReader/XMLWriter are needed for the development suite. GD is useful if you add raster images to reports. Start with 128 MiB or more per PHP process and size FPM workers for server memory.

No npm, CDN request or asset compilation is required. Blade uses the included local CSS, JavaScript, icons and font files under public/. The ZIP excludes vendor/; install from composer.lock as shown below.

## 2. Files and database

1. Verify the downloaded ZIP's SHA-256 against the release handoff. Extract it into a staging directory. Run sha256sum -c SHA256SUMS from the directory containing the extracted ipg-facility/ folder.
2. Put the ipg-facility/ directory at /srv/ipg-facility (or adapt all example paths). Run Composer and deployment commands as the account that owns the source, for example deploy. The web service account needs read access to source; it only needs write access to storage/, bootstrap/cache/ and the SQLite database directory if used.
3. Create an empty utf8mb4 database on a maintained MySQL 8 or MariaDB server (MariaDB 10.11 is the example). Use a dedicated account limited to that schema. The account running migrations needs schema changes and foreign-key privileges. No global privileges are needed by the app. Restrict database access to the application host and use TLS for a remote database.
4. For a small, single-server SQLite installation, place an empty file outside the web root, such as /var/lib/ipg-facility/database.sqlite. Allow the application account to write both the file and its directory so SQLite can create journals. Keep it on local disk; test your expected write concurrency before choosing SQLite.

From the new application root, create the environment file once:

    test ! -e .env && (umask 0027; cp .env.production.example .env)

Set APP_URL to the final HTTPS URL. IPG Facility accepts the exact configured host in production, so use that host for health probes too. Keep APP_ENV=production, APP_DEBUG=false and SESSION_SECURE_COOKIE=true. Leave public registration closed unless IPG approves open enrollment. Set DB_CONNECTION=mariadb or mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD. For SQLite, set DB_CONNECTION=sqlite and DB_DATABASE to the absolute file path; remove unused DB host and credential settings. Do not leave a conflicting DB_URL in the service environment.

Configure your SMTP provider in .env: MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD, MAIL_FROM_ADDRESS and MAIL_SCHEME (smtp for STARTTLS where supported, smtps for implicit TLS, per your provider). Verify it can deliver to institutional addresses. The log/array drivers do not deliver mail. APP_TIMEZONE defaults to Asia/Kuching; preserve it when upgrading.

Example permissions for a deployment account in the www-data group (adapt to your FPM pool):

    chgrp www-data .env
    chmod 0640 .env
    chgrp -R www-data storage bootstrap/cache
    chmod -R g+rwX storage bootstrap/cache
    find storage bootstrap/cache -type d -exec chmod g+s {} +
    umask 0002

Keep secrets and backups readable only by authorized accounts. Use compatible group permissions or ACLs for CLI, worker and web processes. Do not use chmod 777.

## 3. Install and initialize

Check the configured database and run these commands from the application root:

    composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
    composer check-platform-reqs --no-dev
    php artisan key:generate --force
    php artisan migrate --force --no-interaction
    php artisan migrate:status
    php artisan app:create-admin
    php artisan optimize

Generate an APP_KEY only for this fresh installation. Back it up with .env; retain the same key during updates and restoration. The administrator command prompts for identity and a password of at least 12 characters. Use an institution-managed mailbox and a unique password. No seed is required; add categories, facilities and staff in the admin UI. Never run demo seeders, migrate:fresh, migrate:reset or db:wipe on the server.

## 4. HTTPS and background work

Set the document root to **/srv/ipg-facility/public**, never the application directory. Use [nginx.conf.example](deploy/nginx.conf.example), replacing hostname, paths, TLS certificate and FPM socket. Obtain a certificate for the domain using your institution's CA or an ACME client before enabling the HTTPS virtual host. Enable certificate renewal. Test with nginx -t, then reload Nginx. If using Apache, set the same public document root, enable rewrite and FPM, allow the public .htaccess directives or configure equivalent rules in the virtual host, and redirect HTTP to HTTPS. For reverse proxy deployments, configure trusted proxy addresses explicitly and verify scheme, host and cookies before launch.

The provided production template uses a **database queue**. Install [ipg-facility-queue.service](deploy/ipg-facility-queue.service) in /etc/systemd/system/ after reviewing PHP path, user and application path:

    sudo systemctl daemon-reload
    sudo systemctl enable --now ipg-facility-queue
    sudo systemctl status ipg-facility-queue

The sample worker's timeout is 60 seconds, below the database queue's 90-second retry interval. Monitor failed jobs with php artisan queue:failed; retry specific failed jobs after fixing delivery. Do not retry jobs blindly.

Install [ipg-facility.cron](deploy/ipg-facility.cron) as /etc/cron.d/ipg-facility, owned by root and mode 0644. Run the scheduler on one host. It expires overdue pending requests, sends eligible reminders and queues health probes. Keep cron error output available in your server logs or cron mail. Check php artisan schedule:list. Run php artisan schedule:run as the application account on staging to check it works.

## 5. Verify before handing over

- Request https://YOUR-HOST/up and check HTTP 200. This shows Laravel booted. Check php artisan migrate:status for a database/schema check.
- Confirm /.env, /composer.json, /storage/logs/ and arbitrary .php paths are inaccessible over HTTPS. Check that the login cookie is Secure and HttpOnly.
- Sign in as the provisioned administrator. In **System Health**, confirm recent expiration and queue heartbeats. Review failed jobs and verify email with a real password reset and a booking notification for a staging account.
- As staff, create a future booking and verify a conflicting request fails. As admin, approve and reject synthetic requests, review a reschedule and check CSV/PDF exports. Open the weekly board and detailed grid as both roles; only the booker and administrators can open the private booking record.
- Rehearse a verified backup restore and a source rollback. Remove synthetic staging accounts or use a clean production database. Check latency and PDF rendering at the intended server limits.

## 6. Back up and restore

Take encrypted off-host backups of the database, .env, uploads (if used) and the matching application release. Limit access, set retention and test restoration into a separate database or directory.

For SQLite, from the app root (change both paths to suit your installation):

    python3 scripts/backup-sqlite.py --database /var/lib/ipg-facility/database.sqlite \
        --output-dir /var/backups/ipg-facility

The helper uses SQLite's online backup API and checks the copy. To restore, stop web writes, queue workers and the scheduler. Preserve the current SQLite file and its sidecars separately; restore a verified snapshot into a new location and set DB_DATABASE to that path. Check owner and directory permissions, then rebuild config cache and verify the data before reopening. Never mix old -wal/-shm files with a restored snapshot.

For MariaDB/MySQL, use a database-native consistent dump or managed snapshot, with credentials supplied via a private option file or interactive prompt. During maintenance, a DBA can use mariadb-dump --single-transaction --quick -u USER -p DATABASE > /private/path/ipg.sql (use mysqldump on MySQL). Restore with the matching client into a separate empty schema; verify migrations, row counts and sampled booking history. Switch to the restored DB only after review. A restore can lose writes made since the snapshot; record the recovery point.

## 7. Updates and troubleshooting

See [UPDATE.md](UPDATE.md) for the baseline guard, source backup, dependencies, cache preparation and rollback. A source patch never migrates the database for you.

| Symptom | Check |
| --- | --- |
| 400 / invalid host | Match the requested host to APP_URL and the Nginx server_name. Rebuild config cache after editing .env. |
| 419 / login loop | Check HTTPS, Secure cookies, APP_URL, session table and session driver. Retain the existing key when updating. |
| 500 / missing booking_events | Check PHP and FPM extensions, writable paths, the active DB and php artisan migrate:status. Back up the DB before applying pending migrations. |
| 502 / PHP unavailable | Check FPM service, socket path and Nginx/FPM error logs. |
| Expiry or reminders stale | Check cron, account permissions and schedule:list. A fresh /up response alone does not validate cron. |
| Mail or queue stale | Check the worker service, jobs/failed_jobs, SMTP settings and reachable mail provider. Restart workers after updating cached config. |
| Large PDF | Use the summary, detail parts or full CSV. If a 50-record part fails, check PHP memory, fonts, FPM timeout and logs with representative data. |

References: [Laravel deployment](https://laravel.com/framework/docs/deployment), [Debian 13 release notes](https://www.debian.org/releases/trixie/release-notes/whats-new.en.html), [PHP supported versions](https://www.php.net/supported-versions.php).
