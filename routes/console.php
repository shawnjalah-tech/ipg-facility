<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;
use App\Jobs\QueueHealthProbe;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The server must run `php artisan schedule:work` in development, or
// cron `php artisan schedule:run` each minute in production.
// A successful completion marker distinguishes a running scheduler from a healthy
// expiration command. Do not mark success when the command exits with an error.
Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping()
    ->onSuccess(function () {
        Storage::disk('local')->put('system-health/expiration.json', json_encode([
            'succeeded_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
    });

// Enqueued every minute; processed only when a queue worker is actually running.
Schedule::job(new QueueHealthProbe)->everyMinute();

// Review reminders are idempotent and persisted in booking_attention_reminders.
Schedule::command('bookings:attention-reminders')->everyFiveMinutes()->withoutOverlapping();
