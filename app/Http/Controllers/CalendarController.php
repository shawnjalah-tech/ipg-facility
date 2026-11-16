<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Models\FacilityClosure;
use Carbon\Carbon;
use App\Services\FacilityCalendarBoardService;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    /** The weekly facility board is the default; detailed slots remain available for precision. */
    public function index(Request $request, FacilityCalendarBoardService $boardService)
    {
        if ($request->query('view') === 'slots') {
            return $this->slots($request);
        }

        $data = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'],
            'category' => ['nullable', 'integer'],
            'facility' => ['nullable', 'integer'],
            'reschedule' => ['nullable', 'integer'],
            'slot' => ['nullable', 'in:30,60'],
            'duration' => ['nullable', 'in:30,60,120,180'],
            'view' => ['nullable', 'in:board'],
        ]);
        $weekStart = isset($data['week'])
            ? Carbon::parse($data['week'])->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        $rescheduleBooking = $this->authorizedReschedule($request, $data['reschedule'] ?? null);
        $categories = Category::orderBy('name')->get();
        $categoryId = $categories->contains('id', (int) ($data['category'] ?? 0)) ? (int) $data['category'] : null;
        $facilityId = (int) ($data['facility'] ?? 0);
        $facilityQuery = Facility::with('category')->where('is_active', true);
        if ($rescheduleBooking) {
            $facilityQuery->whereKey($rescheduleBooking->facility_id);
        } elseif ($facilityId) {
            $facilityQuery->whereKey($facilityId);
        } elseif ($categoryId) {
            $facilityQuery->where('category_id', $categoryId);
        }
        $facilities = $facilityQuery->orderBy('name')->get();
        $groups = $categories->map(fn ($category) => [
            'category' => $category,
            'facilities' => $facilities->where('category_id', $category->id)->values(),
        ])->filter(fn ($group) => $group['facilities']->isNotEmpty())->values();
        $board = $boardService->week($facilities, $weekStart);
        $days = collect(range(0, 6))->map(fn ($i) => $weekStart->copy()->addDays($i));
        $duration = (int) ($data['duration'] ?? 60);

        return view('calendar.index', compact(
            'weekStart', 'weekEnd', 'days', 'groups', 'categories', 'categoryId',
            'facilities', 'board', 'rescheduleBooking', 'duration'
        ));
    }

    public function day(Request $request, FacilityCalendarBoardService $boardService)
    {
        $data = $request->validate([
            'facility' => ['required', 'integer', 'exists:facilities,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'reschedule' => ['nullable', 'integer'],
            'fragment' => ['nullable', 'in:1'],
        ]);
        $facility = Facility::with('category')->where('is_active', true)->findOrFail($data['facility']);
        $day = Carbon::createFromFormat('Y-m-d', $data['date'])->startOfDay();
        $rescheduleBooking = $this->authorizedReschedule($request, $data['reschedule'] ?? null);
        if ($rescheduleBooking) {
            abort_unless($rescheduleBooking->facility_id === $facility->id, 403);
        }
        $details = $boardService->day($facility, $day);
        $canBook = $boardService->hasOpenTime($day, $details['bookings'], $details['closures']);
        $bookUrl = $rescheduleBooking
            ? route('staff.bookings.reschedule.create', ['booking' => $rescheduleBooking->id, 'start_date' => $day->toDateString(), 'end_date' => $day->toDateString()])
            : ($request->user()->isAdmin()
                ? route('admin.bookings.create', ['facility_id' => $facility->id, 'start_date' => $day->toDateString(), 'end_date' => $day->toDateString()])
                : route('staff.bookings.create', ['facility_id' => $facility->id, 'start_date' => $day->toDateString(), 'end_date' => $day->toDateString()]));
        $slotsUrl = route('calendar.index', array_filter([
            'view' => 'slots', 'week' => $day->copy()->startOfWeek()->toDateString(),
            'facility' => $facility->id, 'reschedule' => $rescheduleBooking?->id,
        ]));
        $view = $request->query('fragment') === '1' ? 'calendar._day-panel' : 'calendar.day';
        return view($view, compact('facility', 'day', 'details', 'bookUrl', 'slotsUrl', 'canBook'));
    }

    private function authorizedReschedule(Request $request, ?int $bookingId): ?Booking
    {
        if (! $bookingId) return null;
        $booking = Booking::findOrFail($bookingId);
        abort_unless(! $request->user()->isAdmin() && $booking->user_id === $request->user()->id, 403);
        abort_unless(in_array($booking->status, ['pending', 'approved'], true), 404);
        return $booking;
    }

    /** Original detailed grid preserved as an optional view. */
    private function slots(Request $request)
    {
        $data = $request->validate([
            'week' => ['nullable', 'date_format:Y-m-d'],
            'category' => ['nullable', 'integer'],
            'facility' => ['nullable', 'integer'],
            'reschedule' => ['nullable', 'integer'],
            'slot' => ['nullable', 'in:30,60'],
            'duration' => ['nullable', 'in:30,60,120,180'],
        ]);

        $weekStart = isset($data['week'])
            ? Carbon::parse($data['week'])->startOfWeek(Carbon::MONDAY)
            : now()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $rescheduleBooking = null;
        if (isset($data['reschedule'])) {
            $rescheduleBooking = Booking::findOrFail($data['reschedule']);
            abort_unless(! $request->user()->isAdmin() && $rescheduleBooking->user_id === $request->user()->id, 403);
            abort_unless(in_array($rescheduleBooking->status, ['pending', 'approved'], true), 404);
        }

        $categories = Category::orderBy('name')->get();
        $categoryId = $categories->contains('id', (int) ($data['category'] ?? 0)) ? (int) $data['category'] : null;
        $query = Facility::with('category')->where('is_active', true);
        if ($rescheduleBooking) {
            $query->whereKey($rescheduleBooking->facility_id);
            $categoryId = null;
        } elseif ($categoryId) {
            $query->where('category_id', $categoryId);
        }
        $facilities = $query->orderBy('name')->get();
        $facility = $facilities->firstWhere('id', (int) ($data['facility'] ?? 0)) ?? $facilities->first();

        $days = collect(range(0, 6))->map(fn ($n) => $weekStart->copy()->addDays($n));
        $hours = array_merge(range(6, 23), range(0, 5));
        $slotMinutes = (int) ($data['slot'] ?? 30);
        $duration = (int) ($data['duration'] ?? 60);
        $minutes = $slotMinutes === 60 ? [0] : [0, 30];
        $weekSlots = [];
        $slotBookings = [];
        $counts = ['available' => 0, 'approved' => 0, 'pending' => 0, 'closed' => 0, 'unavailable' => 0];

        if ($facility) {
            // Fetch an extra day so full-duration checks can cross midnight on Sunday.
            $rangeEnd = $weekEnd->copy()->addDay();
            $bookings = Booking::query()
                ->select(['id', 'facility_id', 'user_id', 'start_date', 'end_date', 'start_time', 'end_time', 'purpose', 'status'])
                ->with(['user:id,name'])
                ->where('facility_id', $facility->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $rangeEnd->toDateString())
                ->whereDate('end_date', '>=', $weekStart->toDateString())
                ->get();
            $closures = FacilityClosure::overlapping($facility->id, $weekStart, $rangeEnd->copy()->addDay())->get();
            foreach ($days as $day) {
                $dayKey = $day->toDateString();
                foreach ($hours as $hour) {
                    foreach ($minutes as $minute) {
                        $key = sprintf('%02d:%02d', $hour, $minute);
                        $slotStart = $day->copy()->startOfDay()->addHours($hour)->addMinutes($minute);
                        $slotEnd = $slotStart->copy()->addMinutes($slotMinutes);
                        $requestedEnd = $slotStart->copy()->addMinutes($duration);
                        $type = 'available';
                        // Existing reservations take priority when showing an overlap with a closure.
                        foreach ($bookings as $booking) {
                            if ($slotStart->lt($booking->ends_at) && $booking->starts_at->lt($slotEnd)) {
                                if ($booking->status === 'approved' || $type !== 'approved') {
                                    $type = $booking->status;
                                    $slotBookings[$dayKey][$key] = $booking;
                                }
                                if ($type === 'approved') break;
                            }
                        }
                        if ($type === 'available' && $closures->contains(fn ($c) => $slotStart->lt($c->ends_at) && $c->starts_at->lt($slotEnd))) {
                            $type = 'closed';
                        }
                        if ($type === 'available' && $slotStart->lte(now())) {
                            $type = 'past';
                        }
                        if ($type === 'available' && (
                            $bookings->contains(fn ($b) => $slotStart->lt($b->ends_at) && $b->starts_at->lt($requestedEnd)) ||
                            $closures->contains(fn ($c) => $slotStart->lt($c->ends_at) && $c->starts_at->lt($requestedEnd))
                        )) {
                            $type = 'unavailable';
                        }
                        $weekSlots[$dayKey][$key] = $type;
                        if (isset($counts[$type])) $counts[$type]++;
                    }
                }
            }
        }

        return view('calendar.slots', compact(
            'weekStart', 'weekEnd', 'categories', 'categoryId', 'facilities',
            'facility', 'days', 'hours', 'minutes', 'slotMinutes', 'duration', 'weekSlots', 'slotBookings', 'counts', 'rescheduleBooking'
        ));
    }
}
