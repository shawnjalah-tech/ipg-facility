<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Facility;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FacilityController extends Controller
{
    public function index()
    {
        $categories = Category::with(['facilities' => function ($q) {
            $q->orderBy('name');
        }])
            ->orderBy('name')
            ->get();

        return view('admin.catalog.index', compact('categories'));
    }

    public function create(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        $selectedCategoryId = $request->query('category_id');

        return view('admin.facilities.create', compact('categories', 'selectedCategoryId'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Facility::create($data);

        return redirect()
            ->route('admin.facilities.index')
            ->withFragment('category-'.$data['category_id'])
            ->with('status', 'Facility created successfully.');
    }

    public function show(Facility $facility)
    {
        return redirect()->route('admin.facilities.edit', $facility);
    }

    public function edit(Facility $facility)
    {
        $categories = Category::orderBy('name')->get();
        $affectedBookings = $this->affectedApprovedBookings($facility);

        return view('admin.facilities.edit', compact('facility', 'categories', 'affectedBookings'));
    }

    public function update(Request $request, Facility $facility, BookingService $bookings)
    {
        $data = $this->validated($request, $facility);

        DB::transaction(function () use ($request, $facility, $bookings, $data) {
            $locked = $bookings->lockFacility($facility->id);
            // Check under the same lock as approval so no approval can slip
            // between the acknowledgement check and deactivation.
            if ($locked->is_active && ! $data['is_active']) {
                $affected = $this->affectedApprovedBookings($locked);
                if ($affected->isNotEmpty() && ! $request->boolean('confirm_deactivation')) {
                    throw ValidationException::withMessages([
                        'is_active' => 'This facility has '.$affected->count().' upcoming or ongoing approved booking(s). Review them and confirm deactivation; existing reservations remain approved.',
                    ]);
                }
            }
            $locked->update($data);
        }, 5);

        return redirect()
            ->route('admin.facilities.index')
            ->withFragment('category-'.$data['category_id'])
            ->with('status', 'Facility updated successfully.');
    }

    public function destroy(Facility $facility)
    {
        if ($facility->bookings()->exists()) {
            return back()->with('error',
                'Cannot delete "'.$facility->name.'" — it has booking history. '.
                'Deactivate it instead to retain the history.');
        }

        $facility->delete();

        return redirect()
            ->route('admin.facilities.index')
            ->with('status', 'Facility deleted.');
    }

    /** Upcoming and ongoing approved reservations are not silently cancelled. */
    private function affectedApprovedBookings(Facility $facility): Collection
    {
        return Booking::where('facility_id', $facility->id)
            ->where('status', 'approved')
            ->whereDate('end_date', '>=', today()->toDateString())
            ->with('user')
            ->orderBy('start_date')
            ->get()
            ->filter(fn (Booking $booking) => $booking->ends_at->gt(now()))
            ->values();
    }

    /**
     * Shared validation for store() and update().
     * $facility is null on store, provided on update (so the unique rule ignores itself).
     */
    private function validated(Request $request, ?Facility $facility = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('facilities', 'name')->ignore($facility?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        // Unchecked checkboxes are absent from the request — normalise.
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
