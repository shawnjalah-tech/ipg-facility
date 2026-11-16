<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRescheduleRequest;
use App\Models\User;
use App\Support\LoadTestSandbox;

class LoadTestDashboardController extends Controller
{
    public function index()
    {
        // This dashboard intentionally cannot query, seed, or run against the production database.
        abort_unless(LoadTestSandbox::active(), 404);

        $base = Booking::where('purpose', 'like', '[LOADTEST]%');
        $counts = (clone $base)->selectRaw('status, count(*) as amount')->groupBy('status')->pluck('amount', 'status')->toArray();
        $counts = array_replace(array_fill_keys(['pending', 'approved', 'rejected', 'cancelled', 'expired'], 0), $counts);
        $recent = (clone $base)->with(['facility', 'user'])->latest('id')->limit(12)->get();
        $proposals = BookingRescheduleRequest::whereHas('booking', fn ($q) => $q->where('purpose', 'like', '[LOADTEST]%'))
            ->selectRaw('status, count(*) as amount')->groupBy('status')->pluck('amount', 'status')->toArray();
        $sessions = [
            'booker' => User::where('email', 'like', 'loadtest-booker-%@example.invalid')->count(),
            'viewer' => User::where('email', 'like', 'loadtest-viewer-%@example.invalid')->count(),
            'admin' => User::where('email', 'like', 'loadtest-admin-%@example.invalid')->count(),
        ];
        $stats = $this->lastAggregate(storage_path('app/loadtest/last_stats.csv'));
        $failures = $this->lastFailures(storage_path('app/loadtest/last_failures.csv'));
        $conflicts = $this->approvedOverlaps((clone $base)->where('status', 'approved')->orderBy('facility_id')->get());
        $missingEvents = (clone $base)->whereDoesntHave('events')->count();

        return view('admin.testing.index', compact('counts', 'recent', 'proposals', 'sessions', 'stats', 'failures', 'conflicts', 'missingEvents'));
    }

    private function lastAggregate(string $path): ?array
    {
        if (! is_file($path) || ! ($handle = fopen($path, 'r'))) {
            return null;
        }
        $head = fgetcsv($handle, escape: '');
        $aggregate = null;
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $values = $head && count($head) === count($row) ? array_combine($head, $row) : null;
            if ($values && ($values['Name'] ?? '') === 'Aggregated') {
                $aggregate = $values;
            }
        }
        fclose($handle);

        return $aggregate;
    }

    private function lastFailures(string $path): array
    {
        if (! is_file($path) || ! ($handle = fopen($path, 'r'))) {
            return [];
        }
        $head = fgetcsv($handle, escape: '');
        $items = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $values = $head && count($head) === count($row) ? array_combine($head, $row) : null;
            if ($values) {
                $items[] = $values;
            }
        }
        fclose($handle);

        return array_slice($items, 0, 8);
    }

    private function approvedOverlaps($bookings): int
    {
        $count = 0;
        foreach ($bookings->groupBy('facility_id') as $list) {
            $list = $list->values();
            for ($i = 0; $i < $list->count(); $i++) {
                for ($j = $i + 1; $j < $list->count(); $j++) {
                    if ($list[$i]->starts_at->lt($list[$j]->ends_at) && $list[$j]->starts_at->lt($list[$i]->ends_at)) {
                        $count++;
                    }
                }
            }
        }

        return $count;
    }
}
