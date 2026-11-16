<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\FacilityClosure;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/** Read-only, batched calendar summaries. BookingService remains the authority on conflicts. */
class FacilityCalendarBoardService
{
    public function week(Collection $facilities, Carbon $monday): array
    {
        if ($facilities->isEmpty()) {
            return [];
        }

        $last = $monday->copy()->addDays(6);
        $ids = $facilities->pluck('id')->all();
        $bookings = Booking::query()
            ->select(['id', 'facility_id', 'user_id', 'start_date', 'end_date', 'start_time', 'end_time', 'purpose', 'status'])
            ->with(['user:id,name'])
            ->whereIn('facility_id', $ids)
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('start_date', '<=', $last->toDateString())
            ->whereDate('end_date', '>=', $monday->toDateString())
            ->orderBy('start_date')->orderBy('start_time')->orderBy('id')
            ->get()->groupBy('facility_id');

        $closures = FacilityClosure::query()
            ->select(['id', 'facility_id', 'starts_at', 'ends_at', 'reason'])
            ->whereIn('facility_id', $ids)
            ->where('starts_at', '<', $last->copy()->addDay()->startOfDay())
            ->where('ends_at', '>', $monday->copy()->startOfDay())
            ->orderBy('starts_at')->get()->groupBy('facility_id');

        $board = [];
        foreach ($facilities as $facility) {
            $facilityBookings = $bookings->get($facility->id, collect());
            $facilityClosures = $closures->get($facility->id, collect());
            for ($dayNumber = 0; $dayNumber < 7; $dayNumber++) {
                $day = $monday->copy()->addDays($dayNumber);
                $end = $day->copy()->addDay();
                $matchedBookings = $facilityBookings->filter(
                    fn (Booking $booking) => $booking->starts_at->lt($end) && $booking->ends_at->gt($day)
                )->values();
                $matchedClosures = $facilityClosures->filter(
                    fn (FacilityClosure $closure) => $closure->starts_at->lt($end) && $closure->ends_at->gt($day)
                )->values();
                $past = $end->lte(now());
                $hasBookableSpace = ! $past && $this->hasOpenTime($day, $matchedBookings, $matchedClosures);
                $allClosed = $this->coversEntireDay($day, $matchedClosures);
                $board[$facility->id][$day->toDateString()] = [
                    'bookings' => $matchedBookings,
                    'closures' => $matchedClosures,
                    'past' => $past,
                    'can_book' => $hasBookableSpace,
                    'fully_closed' => $allClosed,
                ];
            }
        }
        return $board;
    }

    /** Determine whether at least one 30-minute interval is free in this calendar day. */
    public function hasOpenTime(Carbon $day, Collection $bookings, Collection $closures): bool
    {
        $dayEnd = $day->copy()->addDay();
        $cursor = $day->copy();
        if ($cursor->lt(now())) {
            $cursor = now()->copy();
        }
        if ($cursor->gte($dayEnd)) {
            return false;
        }
        $busy = [];
        foreach ($bookings as $booking) {
            $busy[] = [$booking->starts_at, $booking->ends_at];
        }
        foreach ($closures as $closure) {
            $busy[] = [$closure->starts_at, $closure->ends_at];
        }
        usort($busy, fn ($a, $b) => $a[0]->getTimestamp() <=> $b[0]->getTimestamp());
        foreach ($busy as [$start, $end]) {
            if ($end->lte($cursor)) {
                continue;
            }
            if ($start->gt($cursor) && $cursor->copy()->addMinutes(30)->lte($start) && $cursor->copy()->addMinutes(30)->lte($dayEnd)) {
                return true;
            }
            if ($end->gt($cursor)) {
                $cursor = $end->copy();
            }
        }
        return $cursor->copy()->addMinutes(30)->lte($dayEnd);
    }

    private function coversEntireDay(Carbon $day, Collection $closures): bool
    {
        $dayEnd = $day->copy()->addDay();
        $cursor = $day->copy();
        $windows = $closures->sortBy('starts_at');
        foreach ($windows as $closure) {
            if ($closure->starts_at->gt($cursor)) {
                return false;
            }
            if ($closure->ends_at->gt($cursor)) {
                $cursor = $closure->ends_at->copy();
            }
            if ($cursor->gte($dayEnd)) {
                return true;
            }
        }
        return false;
    }

    public function day(Facility $facility, Carbon $day): array
    {
        $end = $day->copy()->addDay();
        return [
            'bookings' => Booking::query()
                ->select(['id', 'facility_id', 'user_id', 'start_date', 'end_date', 'start_time', 'end_time', 'purpose', 'status'])
                ->with(['user:id,name'])->where('facility_id', $facility->id)
                ->whereIn('status', ['approved', 'pending'])
                ->whereDate('start_date', '<=', $day->toDateString())
                ->whereDate('end_date', '>=', $day->toDateString())
                ->orderBy('start_date')->orderBy('start_time')->orderBy('id')->get()
                ->filter(fn (Booking $booking) => $booking->starts_at->lt($end) && $booking->ends_at->gt($day))->values(),
            'closures' => FacilityClosure::query()
                ->select(['id', 'facility_id', 'starts_at', 'ends_at', 'reason'])
                ->overlapping($facility->id, $day, $end)
                ->orderBy('starts_at')->get(),
        ];
    }
}
