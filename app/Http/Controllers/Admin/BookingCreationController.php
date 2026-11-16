<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingCreationController extends Controller
{
    /** Administrators can reserve a facility for a registered staff member. */
    public function create(Request $request)
    {
        $request->validate(['staff_q' => ['nullable', 'string', 'max:100']]);
        $categories = Category::with(['facilities' => fn ($query) => $query
            ->where('is_active', true)->orderBy('name')])
            ->orderBy('name')->get();

        // Load only 50 initial choices; JavaScript fetches at most 12 matches per request.
        $staffMembers = User::where('role', 'staff')
            ->when(request()->filled('staff_q'), fn ($q) => $this->filterStaff($q, (string) request('staff_q')))
            ->orderBy('name')->limit(50)->get(['id', 'name', 'email', 'employee_id']);
        if (old('user_id') && ! $staffMembers->contains('id', (int) old('user_id'))) {
            $selected = User::where('role', 'staff')->find((int) old('user_id'));
            if ($selected) $staffMembers->prepend($selected);
        }

        return view('admin.bookings.create', compact('categories', 'staffMembers'));
    }

    private function filterStaff($query, string $term)
    {
        $term = trim($term);
        return $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')
            ->orWhere('employee_id', 'like', '%'.$term.'%')
            ->orWhere('email', 'like', '%'.$term.'%'));
    }

    public function searchStaff(Request $request)
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        return response()->json([
            'results' => $this->filterStaff(User::where('role', 'staff'), $data['q'])
                ->orderBy('name')->limit(12)->get(['id', 'name', 'email', 'employee_id']),
        ]);
    }

    public function store(Request $request, BookingService $bookings)
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'staff')],
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'required_with:end_time', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_with:start_time', 'date_format:H:i'],
            'purpose' => ['nullable', 'string', 'max:500'],
            'repeat' => ['nullable', 'in:none,weekly,monthly'],
            'repeat_count' => ['required_if:repeat,weekly,monthly', 'nullable', 'integer', 'min:2', 'max:26'],
        ]);

        $staff = User::where('role', 'staff')->findOrFail($data['user_id']);
        unset($data['user_id']); // Never mass-assign a submitted owner ID to a booking.

        if (in_array($data['repeat'] ?? 'none', ['weekly', 'monthly'], true)) {
            $series = app(\App\Services\RecurringBookingService::class)->create($request->user(), $staff, $data);
            return redirect()->route('recurring.show', $series)
                ->with('status', 'Recurring reservations created and approved for '.$staff->name.'.');
        }
        $booking = $bookings->createForStaff($request->user(), $staff, $data);

        return redirect()->route('bookings.show', $booking)
            ->with('status', 'Booking #'.$booking->id.' was approved and assigned to '.$staff->name.'.');
    }
}
