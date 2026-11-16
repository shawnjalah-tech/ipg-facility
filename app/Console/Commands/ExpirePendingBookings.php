<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Command;

class ExpirePendingBookings extends Command
{
    protected $signature = 'bookings:expire';

    protected $description = 'Expire unanswered bookings once their scheduled start time has passed';

    public function handle(BookingService $bookings): int
    {
        $count = $bookings->expireOverduePending();
        $this->info("Expired {$count} pending booking(s).");

        return self::SUCCESS;
    }
}
