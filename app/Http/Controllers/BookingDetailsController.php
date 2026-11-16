<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class BookingDetailsController extends Controller
{
    public function show(Request $request, Booking $booking)
    {
        abort_unless($request->user()->isAdmin() || $booking->user_id === $request->user()->id, 403);
        $booking->load(['facility.category', 'user', 'events' => fn ($query) => $query->orderBy('id'), 'rescheduleRequests.reviewer']);

        return view('bookings.show', compact('booking'));
    }

    public function export(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:200', 'status' => 'nullable|in:pending,approved,rejected,cancelled,expired', 'from' => 'nullable|date_format:Y-m-d', 'to' => $request->filled('from') ? 'nullable|date_format:Y-m-d|after_or_equal:from' : 'nullable|date_format:Y-m-d']);
        $query = Booking::with(['facility', 'user'])->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('end_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('start_date', '<=', $request->query('to')))->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ID', 'Facility', 'Booker', 'Start date', 'End date', 'Start time', 'End time', 'Status', 'Purpose', 'Remarks'], ',', '"', '');
            foreach ($query->lazy(200) as $b) {
                $cells = [$b->id, $b->facility?->name, $b->user?->name, $b->start_date->toDateString(), $b->end_date->toDateString(), $b->start_time, $b->end_time, $b->status, $b->purpose, $b->admin_remarks];
                $cells = array_map(fn ($v) => preg_match('/^[\s]*[=+@\-]/u', (string) $v) ? "'".$v : (string) $v, $cells);
                fputcsv($out, $cells, ',', '"', '');
            }
            fclose($out);
        }, 'bookings-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
