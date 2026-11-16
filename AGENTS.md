# IPG Facility: working in this repository

Laravel 13 with Blade and local Bootstrap assets. Use PHP 8.4.1–8.5.x and the versions in composer.lock. Read README.md, docs/DEPLOYMENT.md and docs/UPDATE.md for setup and releases.

- Develop in a separate copy; use bash bin/setup.sh for local SQLite. Run composer install, not composer update, unless the change requires dependency resolution. Keep composer.lock in sync and run composer audit --locked after dependency changes.
- Run composer test and php artisan view:cache in the development copy; run php artisan view:clear after checking. Lint touched PHP with vendor/bin/pint --test <files>. Run node --check on changed scripts. Blade serves public/css, public/js and public/assets directly. There is no Vite or Node build for the app.
- Run node tests/Load/run.mjs from the project root for the isolated 50-user HTTP workload. A fast check uses --duration=0 --rounds=1. Locust is a separate, optional sandbox; see LOAD_TEST_50_USERS_README.md.
- Keep .env files, keys, databases, generated caches, logs, backups, fixture credentials, dependency folders and load results out of changes and distributions. Only .env.example and .env.production.example are templates. Never run test, demo, reset or seed commands against an existing server database.

## Booking and access

BookingService owns conflict checks, status transitions, audit events and rescheduling. Acquire the facility lock before reading reservations in a write transaction; closures and recurring decisions use the same order. Pending and approved bookings hold time; a reschedule holds the current booking until approval. Never bypass these rules in a controller or view. Keep Booking::events() unordered by default so callers can explicitly choose chronological or latest-first order.

Calendar views and fragments may show booker name, purpose and public closure reason to authenticated staff and admins. Do not include administrative remarks or closure notes in calendar queries or payloads. Booking details are owner/admin only; administrative tools and exports are admin only. Preserve staff-only middleware and legacy queue redirects used by notifications and saved links.

## Interface

Use x-page-header once per screen with a title, optional record context and actions. Prefer labels and clear controls over taglines. Keep validation, safety confirmations and meaningful record data visible. Create/reschedule share x-booking-time-fields and public/js/booking-schedule.js; blank start and end times mean all day on the server. Keep native links/forms, stable route names and control IDs, CSRF, labels and keyboard access. GET filters preserve q and reset pagination.
