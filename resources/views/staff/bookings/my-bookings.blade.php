@extends('layouts.app')

@section('title', 'My Bookings')

@include('partials.admin-ui')

@push('styles')
<style>
    /* ---------- Admin remarks callout (on rejected rows) ---------- */
    .remarks-callout {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        margin-top: 8px;
        padding: 8px 10px;
        background: #fdecef;
        border-radius: 8px;
        font-size: 0.75rem;
        color: #8a2338;
        line-height: 1.5;
        max-width: 340px;
    }
    .remarks-callout svg { flex-shrink: 0; margin-top: 2px; }
    .remarks-callout strong { font-weight: 600; }

    /* ---------- Stats header inside the table card ---------- */
    .bookings-summary {
        padding: 16px 20px;
        border-bottom: 1px solid #eef1f4;
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }
    .summary-item {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 60px;
    }
    .summary-item .s-label {
        font-size: 0.6875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #8a93a0;
        font-weight: 600;
    }
    .summary-item .s-value {
        font-size: 1.125rem;
        font-weight: 600;
        color: #343a40;
        line-height: 1.2;
    }
    .summary-item .s-value.pending   { color: #b57f11; }
    .summary-item .s-value.approved  { color: #2e8b52; }
    .summary-item .s-value.rejected  { color: #c73d58; }
    .summary-item .s-value.cancelled { color: #5a6470; }
    .summary-item .s-value.expired   { color: #5a6470; }
</style>
@endpush

@section('content')

    {{-- Page header --}}
    <x-page-header>
        <x-slot:title>My Bookings</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('calendar.index') }}" class="btn-icon btn-icon-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Availability
            </a>
            <a href="{{ route('staff.bookings.create') }}" class="btn-icon btn-icon-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Booking
            </a>
        </x-slot:actions>
    </x-page-header>

    @include('partials.list-search')

    {{-- Filter chips --}}
    @if ($counts['all'] > 0)
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
                    $url = route('staff.my-bookings', array_filter(['q' => request('q'), 'status' => $key], fn ($value) => $value !== null && $value !== ''));
                @endphp
                <a href="{{ $url }}" class="admin-chip {{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif>
                    {{ $f['label'] }}
                    <span class="chip-count">{{ $f['count'] }}</span>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Table card --}}
    <div class="admin-card">
        @if ($bookings->isEmpty() && request()->filled('q'))
<div class="admin-empty"><h6>No matching results</h6><a class="btn btn-outline-secondary" href="{{ url()->current() }}">Clear filters</a></div>
@elseif ($bookings->isEmpty())

            {{-- Empty state --}}
            @if ($status)
                <div class="admin-empty">
                    <div class="admin-empty-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <h6>No {{ $status }} bookings</h6>
                    <a href="{{ route('staff.my-bookings') }}" class="btn-icon btn-icon-outline">
                        Show All Bookings
                    </a>
                </div>
            @else
                <div class="admin-empty">
                    <div class="admin-empty-icon">
                        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <h6>No bookings yet</h6>
                    <a href="{{ route('staff.bookings.create') }}" class="btn-icon btn-icon-primary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Book a Facility
                    </a>
                </div>
            @endif

        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Facility</th>
                            <th>Dates</th>
                            <th>Time</th>
                            <th>Purpose &amp; Remarks</th>
                            <th class="text-center">Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($bookings as $booking)
                            @php
                                $statusClass = match ($booking->status) {
                                    'approved'  => 'soft-badge-success',
                                    'pending'   => 'soft-badge-warning',
                                    'rejected'  => 'soft-badge-danger',
                                    'cancelled' => 'soft-badge-secondary',
                                    default     => 'soft-badge-secondary',
                                };
                                $canCancel = in_array($booking->status, ['pending', 'approved'], true)
                                          && $booking->starts_at->isFuture();
                            @endphp
                            <tr>
                                {{-- Facility --}}
                                <td>
                                    <div class="row-main">{{ $booking->facility->name ?? '—' }}</div>
                                    @if ($booking->facility?->category)
                                        <div class="row-sub">{{ $booking->facility->category->name }}</div>
                                    @endif
                                </td>

                                {{-- Dates --}}
                                <td>
                                    <div class="row-main" style="white-space:nowrap;">
                                        <a href="{{ route('bookings.show', $booking) }}">{{ $booking->date_range }} · #{{ $booking->id }}</a>
                                    </div>
                                    @if ($booking->start_date->isToday())
                                        <div class="row-sub" style="color:#2fa2a6;font-weight:500;">Starts today</div>
                                    @elseif ($booking->start_date->isFuture() && $booking->start_date->diffInDays(now()) <= 3)
                                        <div class="row-sub" style="color:#b57f11;font-weight:500;">
                                            In {{ (int) now()->diffInDays($booking->start_date) }} day{{ (int) now()->diffInDays($booking->start_date) === 1 ? '' : 's' }}
                                        </div>
                                    @elseif ($booking->starts_at->lte(now()) && $booking->ends_at->gt(now()))
                                        <div class="row-sub" style="color:#2e8b52;font-weight:500;">In progress</div>
                                    @endif
                                </td>

                                {{-- Time --}}
                                <td>
                                    @if ($booking->start_time && $booking->end_time)
                                        <span class="time-chip">
                                            {{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }}
                                            –
                                            {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }}
                                        </span>
                                    @else
                                        <span class="text-muted" style="font-size:0.75rem;">Full day</span>
                                    @endif
                                </td>

                                {{-- Purpose + Admin remarks --}}
                                <td>
                                    @if ($booking->purpose)
                                        <div style="color:#5b626b;font-size:0.8125rem;line-height:1.5;">
                                            {{ $booking->purpose }}
                                        </div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif

                                    @if ($booking->status === 'rejected' && $booking->admin_remarks)
                                        <div class="remarks-callout">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                            <span><strong>Admin:</strong> {{ $booking->admin_remarks }}</span>
                                        </div>
                                    @endif
                                </td>

                                {{-- Status --}}
                                <td class="text-center">
                                    <span class="soft-badge {{ $statusClass }}">
                                        {{ ucfirst($booking->status) }}
                                    </span>
                                </td>

                                {{-- Actions --}}
                                <td class="text-end">
                                    <div class="actions-cell">
                                        @if ($canCancel && ! $booking->pending_reschedules_count)
                                            <a class="btn-icon btn-icon-outline" href="{{ route('staff.bookings.reschedule.create', $booking) }}">Reschedule</a>
                                        @elseif($booking->pending_reschedules_count)
                                            <span class="soft-badge soft-badge-warning">Change pending</span>
                                        @endif
                                        @if ($canCancel)
                                            <form action="{{ route('staff.bookings.cancel', $booking) }}"
                                                  method="POST"
                                                  class="d-inline cancel-form"
                                                  data-name="{{ $booking->facility->name ?? 'booking' }}">
                                                @csrf
                                                @method('PUT')
                                                <button type="submit" class="btn-icon btn-icon-outline-warning" title="Cancel booking">
                                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                                                    Cancel
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted" style="font-size:0.75rem;">—</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('admin.partials.delete-confirm-script')
<div class="mt-3">{{ $bookings->links() }}</div>
@endsection
