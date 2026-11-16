<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    /** Bounds both the HTML detail page and each independently rendered PDF. */
    public const BOOKINGS_PER_PART = 50;

    public function index(Request $request)
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $monthStart = $this->parseMonth($request->query('month'));
        $monthEnd = $monthStart->copy()->endOfMonth();
        $query = $this->bookingsForMonth($monthStart, $monthEnd);
        $data = $this->gatherSummary($query, $monthStart, $monthEnd);
        $data['bookings'] = (clone $query)->with(['facility.category', 'user'])
            ->orderBy('start_date')->orderBy('id')->paginate(self::BOOKINGS_PER_PART)->withQueryString();
        $pdfParts = max(1, (int) ceil($data['totalBookings'] / self::BOOKINGS_PER_PART));
        $pdfDetailLimit = self::BOOKINGS_PER_PART;
        $prevMonth = $monthStart->copy()->subMonthNoOverflow();
        $nextMonth = $monthStart->copy()->addMonthNoOverflow();
        $isCurrentMonth = $monthStart->isSameMonth(now());
        $isFutureMonth = $monthStart->isAfter(now()->startOfMonth());

        return view('admin.reports.index', compact(
            'monthStart', 'monthEnd', 'data', 'pdfParts', 'pdfDetailLimit',
            'prevMonth', 'nextMonth', 'isCurrentMonth', 'isFutureMonth'
        ));
    }

    public function download(Request $request)
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'part' => ['nullable', 'integer', 'min:1'],
        ]);
        $monthStart = $this->parseMonth($request->query('month'));
        $monthEnd = $monthStart->copy()->endOfMonth();
        $query = $this->bookingsForMonth($monthStart, $monthEnd);
        $data = $this->gatherSummary($query, $monthStart, $monthEnd);
        $pdfParts = max(1, (int) ceil($data['totalBookings'] / self::BOOKINGS_PER_PART));
        $pdfPart = isset($validated['part']) ? (int) $validated['part'] : null;
        if ($pdfPart !== null && $pdfPart > $pdfParts) {
            throw ValidationException::withMessages(['part' => 'Select a PDF part between 1 and '.$pdfParts.'.']);
        }
        $summaryOnly = $pdfPart === null && $data['totalBookings'] > self::BOOKINGS_PER_PART;
        $offset = (($pdfPart ?? 1) - 1) * self::BOOKINGS_PER_PART;
        $data['bookings'] = $summaryOnly ? collect() : (clone $query)
            ->with(['facility.category', 'user'])->orderBy('start_date')->orderBy('id')
            ->offset($offset)->limit(self::BOOKINGS_PER_PART)->get();
        $detailStart = $data['bookings']->isEmpty() ? 0 : $offset + 1;
        $detailEnd = $offset + $data['bookings']->count();

        $pdf = Pdf::loadView('admin.reports.pdf.monthly', array_merge($data, [
            'monthStart' => $monthStart, 'monthEnd' => $monthEnd,
            'generatedBy' => $request->user()->name, 'generatedAt' => now(),
            'summaryOnly' => $summaryOnly, 'pdfPart' => $pdfPart, 'pdfParts' => $pdfParts,
            'detailStart' => $detailStart, 'detailEnd' => $detailEnd,
        ]));
        $pdf->setPaper('A4', 'portrait');
        $pdf->setOptions([
            'defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true, 'dpi' => 150,
        ]);
        $suffix = $pdfPart !== null ? '-part-'.$pdfPart.'-of-'.$pdfParts : ($summaryOnly ? '-summary' : '');

        return $pdf->download('ipg-gaya-bookings-'.$monthStart->format('Y-m').$suffix.'.pdf');
    }

    private function parseMonth(?string $input): Carbon
    {
        return $input ? Carbon::createFromFormat('!Y-m', $input)->startOfMonth() : now()->startOfMonth();
    }

    private function bookingsForMonth(Carbon $monthStart, Carbon $monthEnd): Builder
    {
        return Booking::query()->whereDate('bookings.start_date', '<=', $monthEnd->toDateString())
            ->whereDate('bookings.end_date', '>=', $monthStart->toDateString());
    }

    /** Aggregate the entire month in SQL without hydrating its booking records. */
    private function gatherSummary(Builder $query, Carbon $monthStart, Carbon $monthEnd): array
    {
        $counts = (clone $query)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $statusCounts = [];
        foreach (['approved', 'pending', 'rejected', 'cancelled', 'expired'] as $status) {
            $statusCounts[$status] = (int) ($counts[$status] ?? 0);
        }
        $totalBookings = (int) $counts->sum();
        $uniqueFacilities = (clone $query)->distinct()->count('bookings.facility_id');
        $uniqueUsers = (clone $query)->distinct()->count('bookings.user_id');
        $byFacility = (clone $query)
            ->leftJoin('facilities', 'bookings.facility_id', '=', 'facilities.id')
            ->leftJoin('categories', 'facilities.category_id', '=', 'categories.id')
            ->selectRaw('bookings.facility_id, facilities.name AS name, categories.name AS category, COUNT(*) AS total')
            ->groupBy('bookings.facility_id', 'facilities.name', 'categories.name')
            ->orderByDesc('total')->orderBy('bookings.facility_id')->limit(5)->get()
            ->map(fn ($row) => ['name' => $row->name ?? 'Unknown facility', 'category' => $row->category ?? '—', 'count' => (int) $row->total]);
        $byDayOfWeek = array_fill_keys(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], 0);
        if ($totalBookings > 0) {
            // At most 31 scalar counts, portable across SQLite/MySQL/PostgreSQL.
            for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
                $byDayOfWeek[$day->format('l')] += (clone $query)
                    ->whereDate('bookings.start_date', '<=', $day->toDateString())
                    ->whereDate('bookings.end_date', '>=', $day->toDateString())->count();
            }
        }
        arsort($byDayOfWeek);

        return compact('statusCounts', 'totalBookings', 'uniqueFacilities', 'uniqueUsers', 'byFacility', 'byDayOfWeek');
    }
}
