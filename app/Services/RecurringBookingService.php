<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\RecurringBookingSeries;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** A series consists of ordinary bookings; every write is atomic. */
class RecurringBookingService
{
    /** The caller validates the input; this method enforces business invariants as well. */
    public function create(User $actor, User $owner, array $data): RecurringBookingSeries
    {
        abort_unless(($actor->id === $owner->id && ! $actor->isAdmin())
            || ($actor->isAdmin() && ! $owner->isAdmin()), 403);
        $frequency = $data['repeat'] ?? 'none';
        $count = filter_var($data['repeat_count'] ?? null, FILTER_VALIDATE_INT);
        if (! in_array($frequency, ['weekly', 'monthly'], true)
            || $count === false || $count < 2 || $count > ($frequency === 'weekly' ? 26 : 12)) {
            throw ValidationException::withMessages(['repeat_count' => 'Choose 2–26 weekly bookings or 2–12 monthly bookings.']);
        }

        $start = CarbonImmutable::parse($data['start_date'])->startOfDay();
        $end = CarbonImmutable::parse($data['end_date'])->startOfDay();
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'End date cannot precede start date.']);
        }
        $span = (int) $start->diffInDays($end);
        $bookings = app(BookingService::class);

        return DB::transaction(function () use ($actor, $owner, $data, $frequency, $count, $start, $span, $bookings) {
            // A single write transaction ensures no partially-created recurring series
            // even if a later occurrence conflicts with a booking or closure.
            $bookings->lockFacility((int) $data['facility_id']);
            $series = RecurringBookingSeries::create([
                'facility_id' => (int) $data['facility_id'],
                'user_id' => $owner->id,
                'created_by' => $actor->id,
                'frequency' => $frequency,
                'occurrence_count' => $count,
            ]);

            for ($i = 0; $i < $count; $i++) {
                // Derive every occurrence from the original date. A monthly series
                // started on Jan 31 returns to the 31st in March where possible.
                $date = $frequency === 'weekly' ? $start->addWeeks($i) : $start->addMonthsNoOverflow($i);
                $occurrence = array_intersect_key($data, array_flip([
                    'facility_id', 'start_time', 'end_time', 'purpose',
                ]));
                $occurrence['start_date'] = $date->toDateString();
                $occurrence['end_date'] = $date->addDays($span)->toDateString();
                $occurrence['recurring_series_id'] = $series->id;

                if ($actor->isAdmin()) {
                    $bookings->createForStaff($actor, $owner, $occurrence);
                } else {
                    $bookings->create($owner, $occurrence);
                }
            }

            return $series;
        }, 5);
    }

    /** Existing BookingService rules audit and recheck every decision, atomically. */
    public function decide(RecurringBookingSeries $series, User $admin, string $decision, ?string $reason = null): int
    {
        abort_unless($admin->isAdmin(), 403);
        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw ValidationException::withMessages(['decision' => 'Choose approval or rejection.']);
        }
        if ($decision === 'rejected' && mb_strlen(trim((string) $reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Provide at least five characters explaining the rejection.']);
        }

        return DB::transaction(function () use ($series, $admin, $decision, $reason) {
            // Use the same lock order as individual decisions and closures.
            $bookings = app(BookingService::class);
            $bookings->lockFacility($series->facility_id);
            $pending = Booking::where('recurring_series_id', $series->id)
                ->where('status', 'pending')->orderBy('id')->lockForUpdate()->get();
            if ($pending->isEmpty()) {
                throw ValidationException::withMessages(['decision' => 'No pending occurrences remain in this series.']);
            }
            foreach ($pending as $booking) {
                $bookings->transition($booking, $admin, $decision, $reason);
            }

            return $pending->count();
        }, 5);
    }
}
