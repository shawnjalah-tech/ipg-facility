<?php

return [
    // Only after configuring a real mailer and a running queue worker.
    'admin_email_enabled' => env('ATTENTION_ADMIN_EMAIL_ENABLED', false),
    // Minutes before the booking starts. Two notices at most per booking.
    'reminder_hours' => [24, 3],
];
