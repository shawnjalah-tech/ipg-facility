<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Category;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    /**
     * Show the "create a booking" form.
     */
    public function create()
    {
        $categories = Category::with(['facilities' => function ($q) {
            $q->where('is_active', true)->orderBy('name');
        }])
            ->orderBy('name')
            ->get();

        return view('staff.bookings.create', compact('categories'));
    }

    /**
     * Validate, check for conflicts, create the booking.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'facility_id' => ['required', 'exists:facilities,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_with:start_time', 'date_format:H:i'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'repeat' => ['nullable', 'in:none,weekly,monthly'],
            'repeat_count' => ['required_if:repeat,weekly,monthly', 'nullable', 'integer', 'min:2', 'max:26'],
        ]);

        if (in_array($data['repeat'] ?? 'none', ['weekly', 'monthly'], true)) {
            $series = app(\App\Services\RecurringBookingService::class)->create($request->user(), $request->user(), $data);
            return redirect()->route('recurring.show', $series)
                ->with('status', 'Recurring booking request submitted; each occurrence awaits approval.');
        }
        app(BookingService::class)->create($request->user(), $data);

        return redirect()
            ->route('staff.my-bookings')
            ->with('status', 'Booking request submitted. Awaiting admin approval.');
    }

    /**
     * List the current user's bookings.
     */
    public function myBookings(Request $request)
    {
        $status = $request->query('status');
        $validStatuses = ['pending', 'approved', 'rejected', 'cancelled', 'expired'];

        $baseQuery = Booking::where('user_id', $request->user()->id);

        // Counts for the filter chips — always across ALL of this user's bookings
        $rawCounts = (clone $baseQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $counts = [
            'all' => array_sum($rawCounts),
            'pending' => $rawCounts['pending'] ?? 0,
            'approved' => $rawCounts['approved'] ?? 0,
            'rejected' => $rawCounts['rejected'] ?? 0,
            'cancelled' => $rawCounts['cancelled'] ?? 0,
            'expired' => $rawCounts['expired'] ?? 0,
        ];

        // Filtered list
        $query = (clone $baseQuery)
            ->with(['facility.category'])->withCount(['rescheduleRequests as pending_reschedules_count' => fn ($q) => $q->where('status', 'pending')])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (in_array($status, $validStatuses, true)) {
            $query->where('status', $status);
        } else {
            $status = null;
        }

        $request->validate(['q' => 'nullable|string|max:200']);
        $bookings = $query->search($request->query('q'))->paginate(15)->withQueryString();

        return view('staff.bookings.my-bookings', compact('bookings', 'status', 'counts'));
    }

    /**
     * Cancel one of the current user's own bookings.
     */
    public function cancel(Request $request, Booking $booking)
    {
        app(BookingService::class)->transition($booking, $request->user(), 'cancelled');

        return redirect()->route('staff.my-bookings')->with('status', 'Booking cancelled.');
    }
}
