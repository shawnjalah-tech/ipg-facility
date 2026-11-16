<?php

namespace App\Http\Controllers;

use App\Models\RecurringBookingSeries;
use App\Services\RecurringBookingService;
use Illuminate\Http\Request;

class RecurringBookingController extends Controller
{
    public function index(Request $request)
    {
        $query = RecurringBookingSeries::with(['facility', 'owner'])
            ->withCount(['bookings as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->latest();
        if (! $request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }
        $series = $query->paginate(15)->withQueryString();
        return view('recurring.index', compact('series'));
    }

    public function show(Request $request, RecurringBookingSeries $series)
    {
        abort_unless($request->user()->isAdmin() || $series->user_id === $request->user()->id, 403);
        $series->load(['bookings.facility', 'owner', 'facility', 'creator']);
        return view('recurring.show', compact('series'));
    }

    public function decide(Request $request, RecurringBookingSeries $series, RecurringBookingService $recurring)
    {
        abort_unless($request->user()->isAdmin(), 403);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'reason' => ['required_if:decision,rejected', 'nullable', 'string', 'min:5', 'max:1000'],
        ]);
        $total = $recurring->decide($series, $request->user(), $data['decision'], $data['reason'] ?? null);
        return redirect()->route('recurring.show', $series)
            ->with('status', $total.' pending occurrences '.$data['decision'].'.');
    }
}
