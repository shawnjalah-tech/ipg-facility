<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\FacilityClosure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FacilityClosureController extends Controller
{
    public function index(Request $request)
    {
        $facilities = Facility::orderBy('name')->get(['id', 'name']);
        $closures = FacilityClosure::with(['facility', 'creator'])
            ->when($request->filled('facility_id'), fn ($q) => $q->where('facility_id', $request->integer('facility_id')))
            ->orderByDesc('starts_at')->paginate(15)->withQueryString();
        return view('admin.facilities.closures', compact('facilities', 'closures'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'reason' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'confirm_affected' => ['sometimes', 'boolean'],
        ]);
        $start = Carbon::createFromFormat('Y-m-d\TH:i', $data['starts_at']);
        $end = Carbon::createFromFormat('Y-m-d\TH:i', $data['ends_at']);
        if ($end->lte($start)) {
            throw ValidationException::withMessages(['ends_at' => 'Closure must end after it starts.']);
        }
        if ($start->lte(now())) {
            throw ValidationException::withMessages(['starts_at' => 'Choose a future start time.']);
        }
        if ($start->diffInDays($end) > 90) {
            throw ValidationException::withMessages(['ends_at' => 'A closure cannot exceed 90 days.']);
        }

        DB::transaction(function () use ($request, $data, $start, $end) {
            $facilityId = (int) $data['facility_id'];
            // Serializes closure creation with all booking/approval/rescheduling writes.
            DB::table('facilities')->where('id', $facilityId)->update(['id' => DB::raw('id')]);
            $facility = Facility::whereKey($facilityId)->lockForUpdate()->firstOrFail();
            if (FacilityClosure::overlapping($facilityId, $start, $end)->exists()) {
                throw ValidationException::withMessages(['starts_at' => 'An existing closure overlaps these dates.']);
            }
            $affected = Booking::where('facility_id', $facility->id)
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $end->toDateString())
                ->whereDate('end_date', '>=', $start->toDateString())
                ->get()->filter(fn (Booking $b) => $b->starts_at->lt($end) && $b->ends_at->gt($start));
            if ($affected->isNotEmpty() && ! $request->boolean('confirm_affected')) {
                throw ValidationException::withMessages(['confirm_affected' => 'This closure overlaps '.$affected->count().' pending or approved booking(s). Review affected requests and confirm to continue. Existing reservations will NOT be cancelled.']);
            }
            $facility->closures()->create([
                'created_by' => $request->user()->id,
                'starts_at' => $start, 'ends_at' => $end,
                'reason' => $data['reason'], 'notes' => $data['notes'] ?? null,
            ]);
        }, 5);
        return redirect()->route('admin.facility-closures.index')->with('status', 'Facility closure created. Existing approved bookings were kept for manual review.');
    }

    public function destroy(FacilityClosure $closure)
    {
        DB::transaction(function () use ($closure) {
            DB::table('facilities')->where('id', $closure->facility_id)->update(['id' => DB::raw('id')]);
            $closure->delete();
        }, 5);
        return redirect()->route('admin.facility-closures.index')->with('status', 'Facility closure removed.');
    }
}
