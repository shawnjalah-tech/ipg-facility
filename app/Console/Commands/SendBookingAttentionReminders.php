<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\User;
use App\Services\BookingAttentionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendBookingAttentionReminders extends Command
{
    protected $signature = 'bookings:attention-reminders';
    protected $description = 'Notify admins once when an unanswered booking is within 24h or 3h of start.';

    public function handle(BookingAttentionService $attention): int
    {
        if (! User::where('role', 'admin')->exists()) {
            $this->components->info('No administrators available for reminder delivery.');
            return self::SUCCESS;
        }
        $count = 0;
        $cutoff = now()->addHours(24);
        Booking::where('status', 'pending')
            ->whereDate('start_date', '<=', $cutoff->toDateString())
            ->chunkById(100, function ($bookings) use ($attention, &$count): void {
                foreach ($bookings as $original) {
                    $startsAt = $original->starts_at;
                    if ($startsAt->lte(now()) || $startsAt->gt(now()->addHours(24))) {
                        continue;
                    }
                    $withinThreeHours = $startsAt->lte(now()->addHours(3));
                    $threshold = $withinThreeHours ? '3h' : '24h';
                    DB::transaction(function () use ($original, $attention, $threshold, &$count): void {
                        $booking = Booking::whereKey($original->id)->lockForUpdate()->first();
                        if (! $booking || $booking->status !== 'pending' || $booking->starts_at->lte(now())) {
                            return;
                        }
                        if ($threshold === '24h' && $booking->starts_at->lte(now()->addHours(3))) {
                            return;
                        }
                        if ($threshold === '3h' && $booking->starts_at->gt(now()->addHours(3))) {
                            return;
                        }
                        $created = DB::table('booking_attention_reminders')->insertOrIgnore([
                            'booking_id' => $booking->id,
                            'threshold' => $threshold,
                            'created_at' => now(),
                        ]);
                        if ($created === 1) {
                            // Store database notifications in the same transaction as dedup record.
                            // If persistence fails, both roll back and can be retried.
                            $attention->overdueReminder($booking, $threshold === '3h' ? '3 hours' : '24 hours');
                            $count++;
                        }
                    }, 3);
                }
            });
        $this->components->info('New booking deadline reminders: '.$count);
        return self::SUCCESS;
    }
}
