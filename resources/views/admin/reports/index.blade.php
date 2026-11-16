@extends('layouts.app')

@section('title', 'Monthly Report')

@include('partials.admin-ui')

@push('styles')
<style>
    .month-nav {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .month-nav .month-picker {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 6px 12px;
        border: 1px solid #e3e6ea;
        border-radius: 10px;
        background: #fbfcfd;
    }
    .month-nav .month-picker svg { color: #3eb7ba; flex-shrink: 0; }
    .month-nav .month-picker input[type="month"] {
        border: 0;
        background: transparent;
        font-family: inherit;
        font-size: 0.8125rem;
        font-weight: 500;
        color: #343a40;
        outline: none;
        padding: 4px 4px;
        cursor: pointer;
    }
    .month-nav .month-picker input[type="month"]:focus {
        outline: none;
    }

    /* Big-number summary cards */
    .report-hero {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    @media (max-width: 767.98px) {
        .report-hero { grid-template-columns: repeat(2, 1fr); }
    }
    .hero-stat {
        background: #fff;
        border: 1px solid #eef1f4;
        border-radius: 12px;
        padding: 20px;
        position: relative;
        overflow: hidden;
    }
    .hero-stat::before {
        content: "";
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: #3eb7ba;
    }
    .hero-stat.green::before  { background: #58db83; }
    .hero-stat.amber::before  { background: #f5b225; }
    .hero-stat.purple::before { background: #7a6fbe; }
    .hero-stat .hero-label {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: #8a93a0;
        font-weight: 600;
        margin: 0 0 8px;
    }
    .hero-stat .hero-value {
        font-size: 2rem;
        font-weight: 600;
        color: #343a40;
        margin: 0;
        line-height: 1;
    }

    /* Two-column breakdown */
    .breakdown-grid {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    @media (max-width: 767.98px) {
        .breakdown-grid { grid-template-columns: 1fr; }
    }
    .breakdown-card {
        min-width: 0;
        background: #fff;
        border: 1px solid #eef1f4;
        border-radius: 12px;
        padding: 20px;
    }
    .breakdown-card h6 {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #343a40;
        margin: 0 0 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .status-bar {
        margin-bottom: 12px;
    }
    .status-bar-label {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.75rem;
        color: #5b626b;
        margin-bottom: 6px;
    }
    .status-bar-label .sb-count {
        font-weight: 600;
        color: #343a40;
    }
    .status-bar-track {
        height: 8px;
        background: #f1f3f5;
        border-radius: 4px;
        overflow: hidden;
    }
    .status-bar-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 0.4s ease;
    }
    .status-bar-fill.approved  { background: #58db83; }
    .status-bar-fill.pending   { background: #f5b225; }
    .status-bar-fill.rejected  { background: #ec536c; }
    .status-bar-fill.cancelled { background: #adb5bd; }
    .status-bar-fill.expired   { background: #6c757d; }

    .facility-rank {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #f1f3f5;
        font-size: 0.8125rem;
    }
    .facility-rank:last-child { border-bottom: 0; }
    .facility-rank .rank-num {
        width: 22px; height: 22px;
        border-radius: 6px;
        background: #f1fbfb;
        color: #2fa2a6;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 0.6875rem;
        font-weight: 600;
        flex-shrink: 0;
    }
    .facility-rank .rank-name {
        flex: 1;
        color: #343a40;
        font-weight: 500;
        min-width: 0;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .facility-rank .rank-category {
        font-size: 0.6875rem;
        color: #8a93a0;
        font-weight: 400;
        display: block;
        margin-top: 1px;
    }
    .facility-rank .rank-count {
        font-weight: 600;
        color: #3eb7ba;
        flex-shrink: 0;
        font-variant-numeric: tabular-nums;
    }

    .day-of-week-row {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.75rem;
        color: #5b626b;
        padding: 6px 0;
        border-bottom: 1px solid #f8f9fa;
    }
    .day-of-week-row:last-child { border-bottom: 0; }
    .day-of-week-row .dow-label {
        width: 80px;
        font-weight: 500;
        color: #343a40;
    }
    .day-of-week-row .dow-bar {
        flex: 1;
        height: 6px;
        background: #f1f3f5;
        border-radius: 3px;
        overflow: hidden;
    }
    .day-of-week-row .dow-fill {
        height: 100%;
        background: linear-gradient(90deg, #3eb7ba, #2fa2a6);
        border-radius: 3px;
    }
    .day-of-week-row .dow-count {
        width: 32px;
        text-align: right;
        font-weight: 600;
        color: #343a40;
        font-variant-numeric: tabular-nums;
    }

    /* Preview table */
    .preview-table { min-width: 900px; }
    .preview-table thead th { background: #fafbfc; }
</style>
@endpush

@section('content')

    {{-- Header --}}
    <x-page-header class="report-header">
        <x-slot:title>Monthly Report</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('admin.bookings.export', ['from' => $monthStart->toDateString(), 'to' => $monthEnd->toDateString()]) }}" class="btn-icon btn-icon-outline">Monthly CSV</a>
            <a href="{{ route('admin.reports.monthly.download', ['month' => $monthStart->format('Y-m')]) }}" class="btn-icon btn-icon-primary">{{ $pdfParts > 1 ? 'Summary PDF' : 'Download PDF' }}</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Month picker --}}
    <div class="admin-card" style="margin-bottom: 24px;">
        <div class="admin-card-pad" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div class="month-nav">
                <a href="{{ route('admin.reports.monthly', ['month' => $prevMonth->format('Y-m')]) }}"
                   class="btn-icon btn-icon-outline" title="Previous month" aria-label="Previous month">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                </a>

                <form method="GET" action="{{ route('admin.reports.monthly') }}" class="month-picker">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    <input type="month"
                           name="month" aria-label="Report month"
                           value="{{ $monthStart->format('Y-m') }}"
                           onchange="this.form.submit()">
                </form>

                <a href="{{ route('admin.reports.monthly', ['month' => $nextMonth->format('Y-m')]) }}"
                   class="btn-icon btn-icon-outline" title="Next month" aria-label="Next month">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                </a>

                @if (! $isCurrentMonth)
                    <a href="{{ route('admin.reports.monthly') }}" class="btn-icon btn-icon-outline">
                        This Month
                    </a>
                @endif
            </div>

            <div style="text-align: right;">
                <div style="font-size: 1.125rem; font-weight: 600; color: #343a40;">
                    {{ $monthStart->format('F Y') }}
                </div>

            </div>
        </div>
    </div>

    @if($pdfParts > 1)
        <div class="admin-card">
            <form class="admin-card-pad report-part-form" method="GET" action="{{ route('admin.reports.monthly.download') }}">
                <input type="hidden" name="month" value="{{ $monthStart->format('Y-m') }}">
                <div>
                    <label for="pdf-part">PDF part (of {{ $pdfParts }})</label>
                    <input class="form-control" type="number" id="pdf-part" name="part" min="1" max="{{ $pdfParts }}" value="1" required>
                </div>
                <button class="btn-icon btn-icon-primary" type="submit">Download {{ $pdfDetailLimit }}-booking PDF part</button>
            </form>
        </div>
    @endif

    {{-- Hero stats --}}
    <div class="report-hero">
        <div class="hero-stat">
            <p class="hero-label">Total Requests</p>
            <p class="hero-value">{{ $data['totalBookings'] }}</p>
        </div>
        <div class="hero-stat green">
            <p class="hero-label">Approved</p>
            <p class="hero-value">{{ $data['statusCounts']['approved'] }}</p>
        </div>
        <div class="hero-stat amber">
            <p class="hero-label">Pending</p>
            <p class="hero-value">{{ $data['statusCounts']['pending'] }}</p>
        </div>
        <div class="hero-stat purple">
            <p class="hero-label">Facilities Used</p>
            <p class="hero-value">{{ $data['uniqueFacilities'] }}</p>
        </div>
    </div>

    @if ($data['totalBookings'] === 0)
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                </div>
                <h6>No bookings in {{ $monthStart->format('F Y') }}</h6>
                <a href="{{ route('admin.reports.monthly') }}" class="btn-icon btn-icon-outline">
                    Jump to This Month
                </a>
            </div>
        </div>
    @else

        {{-- Two-column breakdown --}}
        <div class="breakdown-grid">

            {{-- Status breakdown --}}
            <div class="breakdown-card">
                <h6>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    Status Breakdown
                </h6>
                @foreach (['approved', 'pending', 'rejected', 'cancelled', 'expired'] as $status)
                    @php
                        $count = $data['statusCounts'][$status];
                        $pct   = $data['totalBookings'] > 0 ? round(($count / $data['totalBookings']) * 100) : 0;
                    @endphp
                    <div class="status-bar">
                        <div class="status-bar-label">
                            <span>{{ ucfirst($status) }}</span>
                            <span>
                                <span class="sb-count">{{ $count }}</span>
                                <span style="color:#8a93a0; font-weight:400; margin-left:4px;">({{ $pct }}%)</span>
                            </span>
                        </div>
                        <div class="status-bar-track">
                            <div class="status-bar-fill {{ $status }}" style="width: {{ $pct }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Top facilities --}}
            <div class="breakdown-card">
                <h6>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    Top Facilities
                </h6>
                @foreach ($data['byFacility']->take(5) as $idx => $fac)
                    <div class="facility-rank">
                        <span class="rank-num">{{ $idx + 1 }}</span>
                        <span class="rank-name">
                            {{ $fac['name'] }}
                            <span class="rank-category">{{ $fac['category'] }}</span>
                        </span>
                        <span class="rank-count">{{ $fac['count'] }}</span>
                    </div>
                @endforeach
            </div>

        </div>

        {{-- Day of week --}}
        <div class="admin-card" style="margin-bottom: 24px;">
            <div class="admin-card-pad">
                <h6 style="font-size: 0.8125rem; font-weight: 600; color: #343a40; margin: 0 0 14px; display: flex; align-items: center; gap: 8px;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Busiest Days of the Week
                </h6>
                @php
                    $maxDow = max($data['byDayOfWeek']);
                @endphp
                @foreach ($data['byDayOfWeek'] as $day => $count)
                    <div class="day-of-week-row">
                        <span class="dow-label">{{ $day }}</span>
                        <div class="dow-bar">
                            <div class="dow-fill" style="width: {{ $maxDow > 0 ? round(($count / $maxDow) * 100) : 0 }}%;"></div>
                        </div>
                        <span class="dow-count">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Preview table --}}
        <div class="admin-card">
            <div class="admin-toolbar">
                <h6 class="admin-toolbar-title">Bookings in {{ $monthStart->format('F Y') }}</h6>
                <span class="admin-toolbar-spacer"></span>
                <span style="font-size: 0.75rem; color: #8a93a0;">
                    {{ $data['totalBookings'] }} {{ \Illuminate\Support\Str::plural('booking', $data['totalBookings']) }}
                </span>
            </div>
            <div class="admin-table-wrap">
                <table class="admin-table preview-table">
                    <thead>
                        <tr>
                            <th>Dates</th>
                            <th>Facility</th>
                            <th>Booker</th>
                            <th>Purpose</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['bookings'] as $b)
                            @php
                                $statusClass = match ($b->status) {
                                    'approved'  => 'soft-badge-success',
                                    'pending'   => 'soft-badge-warning',
                                    'rejected'  => 'soft-badge-danger',
                                    'cancelled' => 'soft-badge-secondary',
                                    'expired'   => 'soft-badge-secondary',
                                    default     => 'soft-badge-secondary',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <div class="row-main" style="white-space:nowrap;">{{ $b->date_range }}</div>
                                </td>
                                <td>
                                    <div class="row-main">{{ $b->facility->name ?? '—' }}</div>
                                    @if ($b->facility?->category)
                                        <div class="row-sub">{{ $b->facility->category->name }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="row-main">{{ $b->user->name ?? '—' }}</div>
                                    @if ($b->user?->employee_id)
                                        <div class="row-sub">{{ $b->user->employee_id }}</div>
                                    @endif
                                </td>
                                <td class="purpose-cell">
                                    @if ($b->purpose)
                                        <div class="purpose-text">{{ $b->purpose }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="soft-badge {{ $statusClass }}">
                                        {{ ucfirst($b->status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="report-pagination">{{ $data['bookings']->links() }}</div>
    @endif

@endsection

@push('styles')
<style>
    .report-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:16px; margin-bottom:24px; }
    .report-header .page-heading { margin:0; color:#343a40; font-size:1.5rem; font-weight:600; }
    .report-header .page-header-actions { display:flex; flex-wrap:wrap; gap:8px; }
    .report-part-form { display:flex; align-items:end; flex-wrap:wrap; gap:12px; }
    .report-part-form label { display:block; font-weight:500; margin-bottom:6px; }
    .report-part-form input { max-width:160px; }
    .report-pagination { margin:16px 0; }
    @media(max-width:767px) { .report-header { align-items:flex-start; flex-direction:column; } }
</style>
@endpush
