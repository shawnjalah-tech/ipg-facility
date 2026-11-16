<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\FacilityClosure;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Read-only month metrics. All periods are [start, end) in the application timezone. */
class FacilityAnalyticsService
{
    public function forMonth(Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $next = $start->copy()->addMonth();
        $calendarHours = $start->diffInRealMinutes($next) / 60;

        $facilities = Facility::query()->orderBy('name')->orderBy('id')->get(['id', 'name', 'is_active']);
        $rows = [];
        foreach ($facilities as $facility) {
            $rows[$facility->id] = [
                'id' => $facility->id,
                'name' => $facility->name,
                'active' => (bool) $facility->is_active,
                'approved_count' => 0,
                'booked_minutes' => 0,
                'closed_minutes' => 0,
                'calendar_hours' => $calendarHours,
            ];
        }

        $scheduled = Booking::query()->whereDate('start_date', '<', $next->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString());
        $statusCounts = array_fill_keys(['approved', 'pending', 'rejected', 'cancelled', 'expired'], 0);
        foreach ((clone $scheduled)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->get() as $status) {
            $statusCounts[$status->status] = (int) $status->total;
        }
        $recurringCount = (clone $scheduled)->whereNotNull('recurring_series_id')->count();

        // Sorted, per-facility interval merging avoids double-counting when historical
        // administrative corrections or imported data contain overlapping reservations.
        $approved = (clone $scheduled)->where('status', 'approved')
            ->orderBy('facility_id')->orderBy('start_date')->orderBy('start_time')->orderBy('id')
            ->cursor();
        $last = null;
        foreach ($approved as $booking) {
            if (! isset($rows[$booking->facility_id])) {
                continue;
            }
            $rows[$booking->facility_id]['approved_count']++;
            $begin = max($start->timestamp, $booking->starts_at->timestamp);
            $end = min($next->timestamp, $booking->ends_at->timestamp);
            if ($begin >= $end) {
                continue;
            }
            $this->mergeInterval($rows, $last, (int) $booking->facility_id, $begin, $end, 'booked_minutes');
        }
        $this->flushInterval($rows, $last, 'booked_minutes');

        $last = null;
        $closures = FacilityClosure::query()->where('starts_at', '<', $next)
            ->where('ends_at', '>', $start)
            ->orderBy('facility_id')->orderBy('starts_at')->orderBy('id')->cursor();
        foreach ($closures as $closure) {
            if (! isset($rows[$closure->facility_id])) {
                continue;
            }
            $begin = max($start->timestamp, Carbon::parse($closure->starts_at)->timestamp);
            $end = min($next->timestamp, Carbon::parse($closure->ends_at)->timestamp);
            if ($begin >= $end) {
                continue;
            }
            $this->mergeInterval($rows, $last, (int) $closure->facility_id, $begin, $end, 'closed_minutes');
        }
        $this->flushInterval($rows, $last, 'closed_minutes');

        foreach ($rows as &$row) {
            $row['booked_hours'] = round($row['booked_minutes'] / 60, 2);
            $row['closed_hours'] = round($row['closed_minutes'] / 60, 2);
            $row['utilization_percent'] = $calendarHours > 0
                ? round(100 * $row['booked_minutes'] / ($calendarHours * 60), 2) : 0;
            unset($row['booked_minutes'], $row['closed_minutes']);
        }
        unset($row);
        $facilityRows = array_values($rows);
        usort($facilityRows, fn ($a, $b) => ($b['booked_hours'] <=> $a['booked_hours']) ?: strcmp($a['name'], $b['name']));

        // New requests by creation day. Do not confuse submission volume with
        // reservations *scheduled* for this month.
        $dailyRequests = array_fill(1, $start->daysInMonth, 0);
        foreach (Booking::query()->where('created_at', '>=', $start)->where('created_at', '<', $next)
            ->orderBy('id')->cursor(['id', 'created_at']) as $booking) {
            $day = Carbon::parse($booking->created_at)->day;
            $dailyRequests[$day]++;
        }

        // Events (not mutable booking status) count decisions and cancellations
        // processed within this month. First decision per booking this month
        // contributes to average turnaround; corrections stay in event counts.
        $decisions = ['approved' => 0, 'rejected' => 0, 'cancelled' => 0];
        $firstDecided = [];
        $turnaroundSeconds = 0;
        $turnaroundCount = 0;
        // Use raw query-builder rows for joined audit timestamps. Eloquent model
        // hydration/casts are unnecessary here, and can obscure timestamp aliases.
        // Explicitly select each side of the join so request and decision times
        // cannot accidentally be read from the same created_at column.
        $events = DB::table('booking_events AS events')
            ->join('bookings AS requests', 'requests.id', '=', 'events.booking_id')
            ->whereIn('events.new_status', ['approved', 'rejected', 'cancelled'])
            ->where('events.created_at', '>=', $start->toDateTimeString())
            ->where('events.created_at', '<', $next->toDateTimeString())
            ->orderBy('events.id')
            ->select([
                'events.booking_id',
                'events.new_status',
                'events.created_at AS decided_at',
                'requests.created_at AS requested_at',
            ])->cursor();
        foreach ($events as $event) {
            $decisions[$event->new_status]++;
            if (! in_array($event->new_status, ['approved', 'rejected'], true)
                || isset($firstDecided[$event->booking_id])) {
                continue;
            }

            if ($event->requested_at === null || $event->decided_at === null) {
                continue;
            }

            $seconds = Carbon::parse($event->requested_at)
                ->diffInRealSeconds(Carbon::parse($event->decided_at), false);
            if ($seconds < 0) {
                continue; // Invalid historical audit ordering cannot yield negative turnaround.
            }

            $firstDecided[$event->booking_id] = true;
            $turnaroundSeconds += $seconds;
            $turnaroundCount++;
        }

        return [
            'month' => $start,
            'statusCounts' => $statusCounts,
            'scheduledTotal' => array_sum($statusCounts),
            'recurringCount' => $recurringCount,
            'facilityRows' => $facilityRows,
            'totalBookedHours' => round(array_sum(array_column($facilityRows, 'booked_hours')), 2),
            'dailyRequests' => $dailyRequests,
            'requestsCreated' => array_sum($dailyRequests),
            'decisions' => $decisions,
            'turnaroundCount' => $turnaroundCount,
            'avgDecisionHours' => $turnaroundCount > 0 ? round($turnaroundSeconds / $turnaroundCount / 3600, 2) : null,
        ];
    }

    private function mergeInterval(array &$rows, ?array &$last, int $facilityId, int $begin, int $end, string $field): void
    {
        if ($last === null || $last[0] !== $facilityId || $begin > $last[2]) {
            $this->flushInterval($rows, $last, $field);
            $last = [$facilityId, $begin, $end];
            return;
        }
        $last[2] = max($last[2], $end);
    }

    private function flushInterval(array &$rows, ?array &$last, string $field): void
    {
        if ($last !== null) {
            $rows[$last[0]][$field] += (int) round(($last[2] - $last[1]) / 60);
            $last = null;
        }
    }
}
