# IPG Facility finalization — 5 November 2026

**READY FOR SERVER VALIDATION.** A clean production extraction was tested locally with HTTPS, Nginx, PHP-FPM and MariaDB. Validate the intended server's domain, certificates, service accounts, SMTP and workload before launch. No external server was changed.

## Changes

- Simplified the weekly board, day panel, booking forms, queue, recurring pages and health page. Kept the IPG navigation, controls and visual identity. The Poppins fonts are now served locally.
- Kept calendar booker names and purposes visible to signed-in staff and admins without including administrative remarks. Booking decision reasons can still reach the affected booker through the existing private workflow.
- Serialized reschedule approval, recurring decisions and facility deactivation with booking writes so availability and acknowledgement checks run against the correct state.
- Fixed password flash handling and malformed login input. Removed predictable demo and load-lab credentials; the lab uses its own key. Production defaults to secure session cookies and checks the configured host.
- Updated the five affected Symfony components in composer.lock. Removed the unused Laravel welcome page, Vite starter and superseded queue views; legacy queue URLs still redirect.
- Added a production environment template, Nginx/queue/cron examples, deployment and rollback guides, and current AGENTS.md instructions. The test suite rejects unsafe database or cache configuration. No new database migration is needed.

## Verification performed

| Check | Result |
| --- | --- |
| Laravel/PHPUnit on PHP 8.5.12 | 133 tests, 1,229 assertions passed. Covered booking conflicts, approvals/rejections, cancellation, rescheduling, recurring series, closures, both calendars, roles and confidentiality, notifications, analytics, PDF/CSV and migrations. |
| PDF at 128 MiB | All 10 monthly report tests passed; these include a rendered 50-booking detail part and coverage for a 1,200-booking report/CSV. |
| 50-user HTTP workload | 25 booking staff, 20 browsing staff, 5 admins. 10,608 measured requests over 60.13 seconds of mixed activity plus verification; p95 280.18 ms and p99 391.29 ms. Zero unexpected responses, failed checks or overlapping reservations. One winner in each of the two races. |
| Locust 2.47.0 | 40 seconds with 50 successful sign-ins, expected slot conflicts and zero recorded failures. The test executor cannot expose process telemetry; a local QA wrapper skipped only that monitor. |
| Fresh server installation | Installed locked production Composer packages. Migrated an empty MariaDB 10.11 schema (14 migrations), provisioned an admin interactively, cached configuration/routes/views, ran expiration and the database queue, and checked both heartbeats. An independent repeatable-read race rejected a concurrent reschedule conflict. |
| HTTPS and frontend | Nginx and PHP-FPM served the extracted ZIP with local TLS, secure cookies, role checks, local CSS/JS/fonts, calendar pages and reports. Mobile Chromium exercised the day dialog, exact-minute and all-day entry, and repeat controls. PHP lint, Blade compilation and JS syntax checks passed. The baseline Vite build ran; the finalized app serves the included assets directly and does not need a frontend build. |
| Dependency and distribution checks | Composer manifest and platform requirements verified. No advisories reported by Composer audit at verification time. Archives were checked for corruption, checksums and private runtime paths. The patch was tested for dry-run, baseline and payload rejection, installation, backup, repeat installation and rollback. |

## Before going live

- Use [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) for a new host or [docs/UPDATE.md](docs/UPDATE.md) for the patch. Keep the existing app key and database during an upgrade.
- Validate SMTP delivery and password recovery with the institution's provider. Check service permissions, scheduled execution, trusted HTTPS, database backups and restoration. MySQL 8 is configured as an option; the separate server run used MariaDB. Rehearse on your chosen database and PHP build.
- The local load figures describe a test scenario, not server capacity. Run a staging load test against the actual web server, database and mail/queue settings. Monthly PDF detail downloads contain up to 50 bookings per part; use CSV for the entire month.
- Rescheduling follows the existing staff request and admin review workflow for dates and times in the same facility. This release adds no facility transfer workflow.
