<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Facility-booking dashboards. Reconstructed from the existing IPG Blade views
 * after an unrelated project patch overwrote the previous controller.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = Carbon::today();

        if ($user->isAdmin()) {
            $weekStart = $today->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd = $today->copy()->endOfWeek(Carbon::SUNDAY);

            $stats = [
                'facilities_total' => Facility::count(),
                'facilities_active' => Facility::where('is_active', true)->count(),
                'pending' => Booking::where('status', 'pending')->count(),
                'approved_week' => Booking::where('status', 'approved')
                    ->whereDate('start_date', '<=', $weekEnd->toDateString())
                    ->whereDate('end_date', '>=', $weekStart->toDateString())
                    ->count(),
                'staff_count' => User::where('role', 'staff')->count(),
            ];

            $pendingPreview = Booking::with(['facility.category', 'user'])
                ->where('status', 'pending')
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit(5)
                ->get();

            $weekBookings = Booking::with(['facility.category', 'user'])
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $weekEnd->toDateString())
                ->whereDate('end_date', '>=', $weekStart->toDateString())
                ->orderBy('start_date')
                ->orderBy('start_time')
                ->limit(5)
                ->get();

            return view('dashboards.admin', compact(
                'stats', 'pendingPreview', 'weekBookings', 'weekStart', 'weekEnd'
            ));
        }

        $myBookings = Booking::where('user_id', $user->id);

        $stats = [
            'pending' => (clone $myBookings)->where('status', 'pending')->count(),
            'approved' => (clone $myBookings)->where('status', 'approved')->count(),
            'upcoming' => (clone $myBookings)->where('status', 'approved')
                ->whereDate('end_date', '>=', $today->toDateString())->count(),
        ];

        $upcomingBookings = (clone $myBookings)
            ->with(['facility.category'])
            ->whereIn('status', ['pending', 'approved'])
            ->whereDate('end_date', '>=', $today->toDateString())
            ->orderBy('start_date')
            ->orderBy('start_time')
            ->limit(6)
            ->get();

        return view('dashboards.staff', compact('stats', 'upcomingBookings'));
    }
}
