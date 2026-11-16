<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingRescheduleRequest;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingRescheduleController extends Controller
{
    public function show(BookingRescheduleRequest $reschedule)
    {
        $reschedule->load(['booking.facility', 'requester', 'reviewer']);

        return view('admin.bookings.reschedules.show', compact('reschedule'));
    }

    public function decide(Request $request, BookingRescheduleRequest $reschedule, BookingService $service)
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'review_notes' => ['nullable', 'string', 'max:1000', 'required_if:decision,reject', 'min:5'],
        ]);

        $service->decideReschedule(
            $reschedule,
            $request->user(),
            $data['decision'] === 'approve',
            $data['review_notes'] ?? null
        );

        return redirect()->route('admin.bookings.manage', ['tab' => 'reschedules'])
            ->with('status', 'Rescheduling request '.$data['decision'].'d. Booking history has been updated.');
    }
}
