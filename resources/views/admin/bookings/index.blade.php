@extends('layouts.app')

@section('title', 'All Bookings')

@include('partials.admin-ui')

@section('content')

    {{-- Page header --}}
    <x-page-header>
        <x-slot:title>All Bookings</x-slot:title>
        <x-slot:actions>
            <a class="btn-icon btn-icon-outline" href="{{ route('admin.bookings.manage', ['tab' => 'reschedules']) }}">Review rescheduling requests</a>
            @if ($counts['pending'] > 0)
                <a href="{{ route('admin.bookings.manage', ['tab' => 'pending']) }}" class="btn-icon btn-icon-warning">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    Review {{ $counts['pending'] }} Pending
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    @include('partials.list-search')
    <details class="export-controls"><summary>Export CSV</summary>
<form method="GET" action="{{ route('admin.bookings.export') }}" class="d-flex flex-wrap align-items-end mb-3" style="gap:8px">
<input type="hidden" name="q" value="{{ request('q') }}"><input type="hidden" name="status" value="{{ request('status') }}">
<div><label for="export-from">Export from</label><input class="form-control" id="export-from" type="date" name="from"></div><div><label for="export-to">To</label><input class="form-control" id="export-to" type="date" name="to"></div>
<button class="btn btn-outline-primary">Export CSV</button></form>
    </details>

    {{-- Filter chips --}}
    @php
        $filters = [
            null        => ['label' => 'All',       'count' => $counts['all']],
            'pending'   => ['label' => 'Pending',   'count' => $counts['pending']],
            'approved'  => ['label' => 'Approved',  'count' => $counts['approved']],
            'rejected'  => ['label' => 'Rejected',  'count' => $counts['rejected']],
            'cancelled' => ['label' => 'Cancelled', 'count' => $counts['cancelled']],
            'expired'   => ['label' => 'Expired',   'count' => $counts['expired']],
        ];
    @endphp

    <div class="admin-chips" style="margin-bottom: 20px;">
        @foreach ($filters as $key => $f)
            @php
                $isActive = $status === ($key ?: null);
                $url = route('admin.bookings.index', array_filter(['q' => request('q'), 'status' => $key], fn ($value) => $value !== null && $value !== ''));
            @endphp
            <a href="{{ $url }}" class="admin-chip {{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif>
                {{ $f['label'] }}
                <span class="chip-count">{{ $f['count'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Table card --}}
    <div class="admin-card">
        @if ($bookings->isEmpty() && request()->filled('q'))
<div class="admin-empty"><h6>No matching results</h6><a class="btn btn-outline-secondary" href="{{ url()->current() }}">Clear filters</a></div>
@elseif ($bookings->isEmpty())
            <div class="admin-empty">
                <div class="admin-empty-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                </div>
                <h6>No bookings to show</h6>
                @if ($status)
                    <a href="{{ route('admin.bookings.index') }}" class="btn-icon btn-icon-outline">
                        Show All Bookings
                    </a>
                @endif
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Requested By</th>
                            <th>Dates</th>
                            <th>Time</th>
                            <th>Purpose</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            @include('admin.bookings._row', ['booking' => $booking])
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('admin.partials.booking-actions-script')
<div class="mt-3">{{ $bookings->links() }}</div>
@endsection
