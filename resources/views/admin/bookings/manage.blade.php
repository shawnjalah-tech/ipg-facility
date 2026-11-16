@extends('layouts.app')

@section('title', 'Booking Management')

@include('partials.admin-ui')

@section('content')
    <x-page-header>
        <x-slot:title>Booking Management</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('admin.bookings.index') }}" class="btn-icon btn-icon-outline">Booking History</a>
            <a href="{{ route('notifications.index') }}" class="btn-icon btn-icon-outline">Notifications</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Reuse the same dashboard cards as other administrator pages. --}}
    <div class="admin-stat-pills">
        <div class="admin-stat-pill">
            <span class="pill-icon teal" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 2v4m8-4v4M3 10h18"/><path d="m9 16 2 2 4-4"/></svg>
            </span>
            <span class="pill-content"><span class="pill-label">Pending decisions</span><span class="pill-value">{{ $counts['pending'] }}</span></span>
        </div>
        <div class="admin-stat-pill">
            <span class="pill-icon amber" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
            </span>
            <span class="pill-content"><span class="pill-label">Due within 24 hours</span><span class="pill-value">{{ $counts['urgent'] }}</span></span>
        </div>
        <div class="admin-stat-pill">
            <span class="pill-icon purple" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 2v4m8-4v4M3 10h18"/><path d="m8 6 4-2 4 2"/><path d="m8 16 3 3 5-6"/></svg>
            </span>
            <span class="pill-content"><span class="pill-label">Reschedule requests</span><span class="pill-value">{{ $counts['reschedules'] }}</span></span>
        </div>
    </div>

    {{-- Match the filter chips used by Booking History. --}}
    <nav class="admin-chips mb-3" aria-label="Booking management queues">
        @foreach (['pending' => 'Pending', 'urgent' => 'Urgent', 'reschedules' => 'Reschedules'] as $key => $label)
            <a href="{{ route('admin.bookings.manage', ['tab' => $key] + request()->only('q')) }}"
               class="admin-chip {{ $tab === $key ? 'is-active' : '' }}"
               @if ($tab === $key) aria-current="page" @endif>
                {{ $label }} ({{ $counts[$key] }})
            </a>
        @endforeach
    </nav>

    <div class="admin-card">
        <form method="GET" action="{{ route('admin.bookings.manage') }}" class="admin-toolbar">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div style="min-width:220px;flex:1;max-width:500px">
                <label class="form-label mb-1" for="management-search">Search {{ $tab === 'reschedules' ? 'reschedules' : 'bookings' }}</label>
                <input type="search" name="q" id="management-search" class="form-control" maxlength="200"
                       value="{{ $search }}" placeholder="Name, facility or purpose">
            </div>
            <button type="submit" class="btn-icon btn-icon-primary">Search</button>
            @if ($search !== '')
                <a class="btn-icon btn-icon-outline" href="{{ route('admin.bookings.manage', ['tab' => $tab]) }}">Clear search</a>
            @endif
        </form>

        <div class="admin-toolbar">
            <div>
                @php
                    $queueHeading = match ($tab) {
                        'urgent' => 'Urgent booking decisions',
                        'reschedules' => 'Rescheduling requests',
                        default => 'Pending booking requests',
                    };
                @endphp
                <h2 class="admin-toolbar-title">{{ $queueHeading }}</h2>
                <span class="row-sub">
                    @if ($tab === 'urgent')
                        Starts within 24 hours.
                    @elseif ($tab === 'reschedules')
                        Changes take effect on approval.
                    @else
                        Oldest first.
                    @endif
                </span>
            </div>
        </div>

        @if ($tab === 'reschedules')
            @if ($requests->isEmpty())
                <div class="admin-empty">
                    <div class="admin-empty-icon" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 2v4m8-4v4M3 10h18m-14 6h6"/></svg>
                    </div>
                    <h6>{{ $search === '' ? 'No rescheduling requests awaiting review' : 'No matching rescheduling requests' }}</h6>
                    @if ($search !== '')
                        <a href="{{ route('admin.bookings.manage', ['tab' => $tab]) }}" class="btn-icon btn-icon-outline">Clear search</a>
                    @endif
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr>
                            <th>Request</th><th>Requested By</th><th>Current Booking</th><th>Proposed Schedule</th><th class="text-end">Actions</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($requests as $proposal)
                                <tr>
                                    <td>
                                        <div class="row-main">Request #{{ $proposal->id }}</div>
                                        <div class="row-sub">Booking #{{ $proposal->booking_id }}</div>
                                    </td>
                                    <td>
                                        <div class="row-main">{{ $proposal->requester?->name ?? 'Unknown staff' }}</div>
                                        <div class="row-sub">{{ $proposal->booking?->facility?->name ?? 'Facility unavailable' }}</div>
                                    </td>
                                    <td>
                                        <div class="row-main">{{ $proposal->booking?->date_range ?? 'Not available' }}</div>
                                        <div class="row-sub">{{ $proposal->booking?->start_time && $proposal->booking?->end_time ? substr($proposal->booking->start_time, 0, 5).' – '.substr($proposal->booking->end_time, 0, 5) : 'All day' }}</div>
                                    </td>
                                    <td>
                                        <div class="row-main">{{ $proposal->date_range }}</div>
                                        <div class="row-sub">{{ $proposal->time_range }}</div>
                                    </td>
                                    <td class="text-end">
                                        <div class="actions-cell">
                                            <a href="{{ route('admin.reschedules.show', $proposal) }}" class="btn-icon btn-icon-outline">Review request</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @else
            @if ($bookings->isEmpty())
                <div class="admin-empty">
                    <div class="admin-empty-icon" aria-hidden="true">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M8 2v4m8-4v4M3 10h18"/></svg>
                    </div>
                    <h6>{{ $search === '' ? 'No bookings in this queue' : 'No matching bookings' }}</h6>
                    @if ($search !== '')
                        <a href="{{ route('admin.bookings.manage', ['tab' => $tab]) }}" class="btn-icon btn-icon-outline">Clear search</a>
                    @endif
                </div>
            @else
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr>
                            <th>Facility</th><th>Requested By</th><th>Schedule</th><th>Purpose</th><th class="text-center">Priority</th><th class="text-end">Actions</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($bookings as $booking)
                                <tr>
                                    <td>
                                        <a class="row-main" href="{{ route('bookings.show', $booking) }}">#{{ $booking->id }} · {{ $booking->facility?->name ?? 'Facility unavailable' }}</a>
                                        <div class="row-sub">Booking #{{ $booking->id }}</div>
                                    </td>
                                    <td>
                                        <div class="row-main">{{ $booking->user?->name ?? 'Unknown staff' }}</div>
                                        <div class="row-sub">{{ $booking->user?->employee_id ?? '' }}</div>
                                    </td>
                                    <td>
                                        <div class="row-main">{{ $booking->date_range }}</div>
                                        <div class="row-sub">{{ $booking->start_time && $booking->end_time ? substr($booking->start_time, 0, 5).' – '.substr($booking->end_time, 0, 5) : 'All day' }}</div>
                                    </td>
                                    <td class="purpose-cell">
                                        <div class="purpose-text">{{ $booking->purpose ?: '—' }}</div>
                                    </td>
                                    <td class="text-center">
                                        @if ($booking->starts_at->lte(now()))
                                            <span class="soft-badge soft-badge-secondary">Past start</span>
                                        @elseif ($booking->starts_at->lte(now()->addHours(24)))
                                            <span class="soft-badge soft-badge-warning">Due soon</span>
                                        @else
                                            <span class="soft-badge soft-badge-primary">Pending</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="actions-cell">
                                            <a href="{{ route('bookings.show', $booking) }}" class="btn-icon btn-icon-outline">Details</a>
                                            @if ($booking->starts_at->gt(now()) && $booking->facility?->is_active)
                                                <form method="POST" action="{{ route('admin.bookings.approve', $booking) }}" class="d-inline">
                                                    @csrf @method('PUT')
                                                    <button type="submit" class="btn-icon btn-icon-success">Approve</button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('admin.bookings.reject', $booking) }}"
                                                  class="d-inline reject-form" data-name="{{ $booking->user?->name ?? 'this staff member' }}">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="admin_remarks" value="">
                                                <button type="submit" class="btn-icon btn-icon-outline-danger">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>

    <div class="mt-3">
        @if ($tab === 'reschedules')
            {{ $requests->links() }}
        @else
            {{ $bookings->links() }}
        @endif
    </div>

    @include('admin.partials.booking-actions-script')
@endsection
