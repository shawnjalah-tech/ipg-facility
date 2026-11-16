<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingApprovalController extends Controller
{
    /**
     * Full booking list with optional status filter.
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        $validStatuses = ['pending', 'approved', 'rejected', 'cancelled', 'expired'];

        $query = Booking::with(['facility.category', 'user'])
            ->orderByDesc('start_date')
            ->orderByDesc('id');

        if (in_array($status, $validStatuses, true)) {
            $query->where('status', $status);
        } else {
            $status = null; // invalid filter → treat as "all"
        }

        $request->validate(['q' => 'nullable|string|max:200']);
        $bookings = $query->search($request->query('q'))->paginate(15)->withQueryString();

        $counts = [
            'all' => Booking::count(),
            'pending' => Booking::where('status', 'pending')->count(),
            'approved' => Booking::where('status', 'approved')->count(),
            'rejected' => Booking::where('status', 'rejected')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'expired' => Booking::where('status', 'expired')->count(),
        ];

        return view('admin.bookings.index', compact('bookings', 'status', 'counts'));
    }

    /**
     * Show the status editor, including the complete audit history.
     */
    public function editStatus(Booking $booking)
    {
        $booking->load(['facility.category', 'user', 'events' => fn ($query) => $query->orderBy('id')]);

        return view('admin.bookings.edit-status', compact('booking'));
    }

    public function updateStatus(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'status' => ['required', 'in:pending,approved,rejected,cancelled,expired'],
            'expected_status' => ['required', 'in:pending,approved,rejected,cancelled,expired'],
            'admin_remarks' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        app(BookingService::class)->reviseStatus(
            $booking, $request->user(), $data['status'], $data['admin_remarks'], $data['expected_status']
        );

        return redirect()->route('admin.bookings.index')->with('status', 'Booking status updated. The change was added to the booking history.');
    }

    /**
     * Approve a pending booking.
     */
    public function approve(Request $request, Booking $booking)
    {
        app(BookingService::class)->transition($booking, $request->user(), 'approved');

        return back()->with('status', 'Booking approved.');
    }

    public function reject(Request $request, Booking $booking)
    {
        $data = $request->validate(['admin_remarks' => ['required', 'string', 'min:5', 'max:1000']]);
        app(BookingService::class)->transition($booking, $request->user(), 'rejected', $data['admin_remarks']);

        return back()->with('status', 'Booking rejected.');
    }

    public function adminCancel(Request $request, Booking $booking)
    {
        $data = $request->validate(['admin_remarks' => ['nullable', 'string', 'max:1000']]);
        app(BookingService::class)->transition($booking, $request->user(), 'cancelled', $data['admin_remarks'] ?? 'Cancelled by administrator.');

        return back()->with('status', 'Booking cancelled.');
    }
}
