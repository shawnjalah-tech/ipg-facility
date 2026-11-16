<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRescheduleRequest;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingRescheduleController extends Controller
{
    public function create(Request $request, Booking $booking)
    {
        abort_unless(! $request->user()->isAdmin() && $booking->user_id === $request->user()->id, 403);
        $booking->load(['facility', 'rescheduleRequests']);
        return view('staff.bookings.reschedule', compact('booking'));
    }

    public function store(Request $request, Booking $booking, BookingService $service)
    {
        abort_unless(! $request->user()->isAdmin() && $booking->user_id === $request->user()->id, 403);
        $data = $request->validate([
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_with:start_time', 'date_format:H:i'],
        ]);
        $service->requestReschedule($booking, $request->user(), $data);
        return redirect()->route('bookings.show', $booking)
            ->with('status', 'Rescheduling request submitted. Your existing booking stays unchanged until an admin approves the change.');
    }

    public function withdraw(Request $request, BookingRescheduleRequest $reschedule, BookingService $service)
    {
        abort_unless(! $request->user()->isAdmin() && $reschedule->user_id === $request->user()->id, 403);
        $service->withdrawReschedule($reschedule, $request->user());
        return redirect()->route('bookings.show', $reschedule->booking_id)
            ->with('status', 'Rescheduling request withdrawn. Your existing booking was not changed.');
    }
}
