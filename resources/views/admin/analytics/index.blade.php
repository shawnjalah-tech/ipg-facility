@extends('layouts.app')

@section('title', 'Facility Analytics')

@include('partials.admin-ui')

@push('styles')
<style>
    .analytics-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 22px; }
    .analytics-stat { padding: 20px; background: #fff; border: 1px solid #edf0f3; border-radius: 12px; }
    .analytics-stat span { font-size: .75rem; color: #69737e; display: block; }
    .analytics-stat strong { font-size: 1.75rem; color: #253442; display: block; margin-top: 7px; }
    .analytics-panel { padding: 20px; }
    .analytics-chart { display: flex; flex-wrap: wrap; gap: 2px; align-items: end; min-height: 96px; margin: 16px 0 4px; }
    .analytics-day { flex: 1 0 6px; min-width: 6px; max-width: 30px; background: #42b8bb; border-radius: 3px 3px 0 0; }
    .analytics-rail { width: 100px; height: 7px; background: #eaf1f3; border-radius: 20px; overflow: hidden; }
    .analytics-fill { height: 100%; background: #42b8bb; }
    .analytics-legend { font-size: .75rem; color: #69737e; }
    @media(max-width: 900px) { .analytics-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media(max-width: 540px) { .analytics-grid { grid-template-columns: 1fr; } }
</style>
@endpush

@section('content')
<x-page-header>
    <x-slot:title>Facility Analytics</x-slot:title>
    <x-slot:actions>
        <a class="btn-icon btn-icon-outline" href="{{ route('admin.reports.monthly', ['month' => $month->format('Y-m')]) }}">Monthly Report</a>
        <a class="btn-icon btn-icon-primary" href="{{ route('admin.analytics.export', ['month' => $month->format('Y-m')]) }}">Export Utilization CSV</a>
    </x-slot:actions>
</x-page-header>

<div class="admin-card" style="margin-bottom:22px;">
    <div class="admin-card-pad" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <a class="btn-icon btn-icon-outline" href="{{ route('admin.analytics.index', ['month' => $prevMonth->format('Y-m')]) }}" aria-label="Previous month">Previous</a>
        <form action="{{ route('admin.analytics.index') }}" method="GET" style="display:flex;align-items:center;gap:8px;">
            <label for="analytics-month">Month</label>
            <input class="admin-input" id="analytics-month" name="month" type="month" value="{{ $month->format('Y-m') }}" required>
            <button class="btn-icon btn-icon-primary" type="submit">View</button>
        </form>
        <a class="btn-icon btn-icon-outline" href="{{ route('admin.analytics.index', ['month' => $nextMonth->format('Y-m')]) }}" aria-label="Next month">Next</a>
    </div>
</div>

<div class="analytics-grid">
    <div class="analytics-stat"><span>Reservations intersecting month</span><strong>{{ number_format($data['scheduledTotal']) }}</strong></div>
    <div class="analytics-stat"><span>Approved booked hours</span><strong>{{ number_format($data['totalBookedHours'], 1) }}</strong></div>
    <div class="analytics-stat"><span>New requests submitted</span><strong>{{ number_format($data['requestsCreated']) }}</strong></div>
    <div class="analytics-stat"><span>Mean decision turnaround</span><strong>{{ $data['avgDecisionHours'] === null ? 'N/A' : number_format($data['avgDecisionHours'], 1).' h' }}</strong></div>
</div>

<div class="admin-card" style="margin-bottom:20px;">
    <div class="analytics-panel">
        <h3 style="font-size:1rem;margin:0 0 10px;">Submission trend</h3>
        <p class="analytics-legend">Bookings created each day of {{ $month->format('F Y') }} (not their scheduled dates)</p>
        @php
            $dailyMax = max(1, ...array_values($data['dailyRequests']));
        @endphp
        <div class="analytics-chart" role="img" aria-label="Daily requests: {{ implode(', ', array_values($data['dailyRequests'])) }}">
            @foreach($data['dailyRequests'] as $day => $count)
                <div class="analytics-day" title="Day {{ $day }}: {{ $count }} requests" style="height:{{ $count ? max(5, round($count / $dailyMax * 92)) : 2 }}px;"></div>
            @endforeach
        </div>
        <p class="analytics-legend">Approved decisions: {{ $data['decisions']['approved'] }} · Rejected decisions: {{ $data['decisions']['rejected'] }} · Cancellation events: {{ $data['decisions']['cancelled'] }} · Recurring occurrences scheduled this month: {{ $data['recurringCount'] }}</p>
        <p class="analytics-legend">Turnaround uses the first approved/rejected event per booking within the selected month; later status corrections may also contribute to the event totals.</p>
    </div>
</div>

<div class="admin-card">
    <div class="analytics-panel">
        <h3 style="font-size:1rem;margin:0 0 12px;">Facility utilization</h3>
        <p class="analytics-legend">Approved booking hours divided by all calendar hours in the month (24 hours/day). Maintenance hours are reported separately and are not subtracted from the denominator. Overlapping intervals are counted only once.</p>
        <div style="overflow-x:auto;">
            <table class="admin-table" style="width:100%;">
                <thead><tr><th>Facility</th><th>Approved bookings</th><th>Booked hours</th><th>Closed hours</th><th>Utilization</th></tr></thead>
                <tbody>
                    @forelse ($data['facilityRows'] as $facility)
                        <tr>
                            <td>{{ $facility['name'] }} @unless($facility['active'])<span class="admin-chip">Inactive</span>@endunless</td>
                            <td>{{ $facility['approved_count'] }}</td>
                            <td>{{ number_format($facility['booked_hours'], 2) }}</td>
                            <td>{{ number_format($facility['closed_hours'], 2) }}</td>
                            <td><div style="display:flex;align-items:center;gap:10px;">
                                <div class="analytics-rail" aria-hidden="true"><div class="analytics-fill" style="width:{{ min(100, $facility['utilization_percent']) }}%;"></div></div>
                                <span>{{ number_format($facility['utilization_percent'], 2) }}%</span>
                            </div></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">No facilities found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
