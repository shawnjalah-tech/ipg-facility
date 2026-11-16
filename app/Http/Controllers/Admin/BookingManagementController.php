<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingRescheduleRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BookingManagementController extends Controller
{
    /** One admin queue for pending bookings, urgent bookings, and reschedules. */
    public function index(Request $request)
    {
        $data = $request->validate([
            'tab' => ['nullable', 'in:pending,urgent,reschedules'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $tab = $data['tab'] ?? 'pending';
        $search = trim($data['q'] ?? '');
        $now = now();

        // Counts describe the entire queue, even when the current tab is searched.
        $counts = [
            'pending' => Booking::where('status', 'pending')->count(),
            'urgent' => $this->urgentPending($now)->count(),
            'reschedules' => BookingRescheduleRequest::where('status', 'pending')->count(),
        ];

        if ($tab === 'reschedules') {
            $query = BookingRescheduleRequest::with(['requester', 'booking.facility'])
                ->where('status', 'pending')
                ->oldest();

            if ($search !== '') {
                $query->where(function (Builder $builder) use ($search) {
                    $builder->whereHas('requester', fn (Builder $u) => $u->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('booking.facility', fn (Builder $f) => $f->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('booking', fn (Builder $b) => $b->where('purpose', 'like', '%'.$search.'%'));
                    if (ctype_digit($search)) {
                        $builder->orWhere('booking_id', (int) $search);
                    }
                });
            }
            $requests = $query->paginate(15)->withQueryString();
            $bookings = null;
        } else {
            $query = $tab === 'urgent'
                ? $this->urgentPending($now)->orderBy('start_date')->orderBy('start_time')->orderBy('id')
                : Booking::where('status', 'pending')->orderBy('created_at')->orderBy('id');
            $bookings = $query->with(['facility', 'user'])->search($search)
                ->paginate(15)->withQueryString();
            $requests = null;
        }

        return view('admin.bookings.manage', compact('tab', 'search', 'counts', 'bookings', 'requests'));
    }

    /**
     * Urgent = starts in (now, now + 24 hours]. Compare dates and clock times
     * separately so the query works on both SQLite and production SQL drivers.
     * All-day reservations (null start_time) start at 00:00.
     */
    private function urgentPending($current): Builder
    {
        $deadline = $current->copy()->addHours(24);

        return Booking::query()->where('status', 'pending')
            ->where(function (Builder $builder) use ($current, $deadline) {
                $builder->where(function (Builder $today) use ($current) {
                    $today->whereDate('start_date', $current->toDateString())
                        ->whereTime('start_time', '>', $current->format('H:i:s'));
                })->orWhere(function (Builder $tomorrow) use ($deadline) {
                    $tomorrow->whereDate('start_date', $deadline->toDateString())
                        ->where(function (Builder $times) use ($deadline) {
                            $times->whereTime('start_time', '<=', $deadline->format('H:i:s'))
                                ->orWhereNull('start_time');
                        });
                });
            });
    }
}
