<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FacilityAnalyticsService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request, FacilityAnalyticsService $analytics)
    {
        $month = $this->month($request);
        $data = $analytics->forMonth($month);
        $prevMonth = $month->copy()->subMonthNoOverflow();
        $nextMonth = $month->copy()->addMonthNoOverflow();

        return view('admin.analytics.index', compact('data', 'month', 'prevMonth', 'nextMonth'));
    }

    public function export(Request $request, FacilityAnalyticsService $analytics)
    {
        $month = $this->month($request);
        $data = $analytics->forMonth($month);

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'wb');
            fputcsv($out, ['Facility', 'Active', 'Approved bookings', 'Booked hours',
                'Maintenance hours', 'Calendar hours', 'Utilization %']);
            foreach ($data['facilityRows'] as $row) {
                fputcsv($out, [
                    $this->safeCell($row['name']),
                    $row['active'] ? 'Yes' : 'No',
                    $row['approved_count'],
                    $row['booked_hours'],
                    $row['closed_hours'],
                    $row['calendar_hours'],
                    $row['utilization_percent'],
                ]);
            }
            fclose($out);
        }, 'ipg-facility-utilization-'.$month->format('Y-m').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function month(Request $request): Carbon
    {
        $values = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return isset($values['month'])
            ? Carbon::createFromFormat('!Y-m', $values['month'])->startOfMonth()
            : now()->startOfMonth();
    }

    private function safeCell(?string $value): string
    {
        $value = (string) $value;
        // CSV viewers may interpret formula-like facility names as commands.
        return preg_match('/^\s*[=+\-@\t\r]/u', $value) ? "'".$value : $value;
    }
}
