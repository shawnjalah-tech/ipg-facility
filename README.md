# IPG Facility

Facility booking and approval for IPG Gaya. Staff can check availability, request one or recurring bookings, cancel upcoming reservations and propose a new date or time. Administrators manage facilities, closures, users, decisions and reports.

- [Install on a new server](docs/DEPLOYMENT.md)
- [Update an existing installation or roll back](docs/UPDATE.md)
- [Release notes and verification](RELEASE_NOTES.md)
- [50-user HTTP test](tests/Load/README.md) · [Locust and the Load Test Lab](LOAD_TEST_50_USERS_README.md)

## Local development

Use a local copy with PHP **8.4.1–8.5.x**, Composer 2, PDO SQLite and the PHP extensions listed in the [server guide](docs/DEPLOYMENT.md). Run from the directory containing artisan:

    bash bin/setup.sh
    php artisan app:create-admin
    composer dev

Open http://127.0.0.1:8000. The script installs locked dependencies, creates a local environment and key only when missing, and migrates the local SQLite database. On an empty, disposable local database, use the --demo option to add sample facilities, staff and bookings. Demo staff get random passwords; use the administrator you provision to set a password for any sample staff account you need. Never seed a server database.

Use separate terminals for php artisan queue:work --tries=3 and php artisan schedule:work if you need email jobs and scheduled work locally. The local log mail transport does not deliver email. Do not share its logs.

Run composer test in an uncached development copy. Feature tests require in-memory SQLite and use an array mailer, in-memory sessions/cache and a synchronous queue. The test base refuses unsafe settings before RefreshDatabase runs. Node.js 20+ is needed only for the HTTP load harness.

## Booking rules

- Pending and approved bookings reserve time; adjacent slots may touch. All-day dates are inclusive. A timed multi-day booking occupies one continuous interval. A request spans at most 30 calendar days.
- Staff can cancel their own upcoming bookings and request a new date/time for the same facility. The current reservation holds until an administrator approves the change. Administrators can correct status with an audited reason.
- Recurring series use ordinary bookings. A conflict blocks the series creation or bulk approval as a unit. Individual occurrences can be managed separately. Closures block new overlapping bookings and keep existing reservations for review.
- The weekly board and detailed grid show active facilities. Signed-in staff and administrators can see booker names and purposes in calendar details. Calendars omit administrative remarks and closure notes. Booking history and decision reasons are available to the booker and administrators; reasons entered on a decision can be sent to the affected booker. Exports are administrator-only.
- Staff and facility records with booking history cannot be deleted. Deactivate facilities to stop new requests. Default time zone: Asia/Kuching.

## Frontend

Blade uses the CSS, JavaScript, icons and fonts included under public/. No Node installation or asset build is needed for the app. Poppins is served locally under the SIL Open Font License. Existing bundled Bootstrap, jQuery, metisMenu, SimpleBar, Waves, SweetAlert2 and Material Design Icons assets retain their supplied notices.

-shon
