@extends('layouts.app')

@section('title', 'Dashboard')

@include('dashboards._styles')

@section('content')

    <x-page-header>
        <x-slot:title>Dashboard</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('admin.facilities.create') }}" class="btn btn-outline-secondary">New Facility</a>
            <a href="{{ route('admin.categories.create') }}" class="btn btn-outline-secondary">New Category</a>
            <a href="{{ route('admin.bookings.manage') }}" class="btn btn-primary">Booking Management ({{ $stats['pending'] }} pending)</a>
        </x-slot:actions>
    </x-page-header>

    {{-- ---------- Stat cards ---------- --}}
    <div class="row">

        <div class="col-md-6 col-xl-3">
            <div class="mini-stat teal">
                <div class="mini-stat-body">
                    <i class="mdi mdi-office-building mini-stat-icon"></i>
                    <div class="mini-stat-label">Facilities</div>
                    <div class="mini-stat-value">{{ $stats['facilities_total'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <a href="{{ route('admin.bookings.manage', ['tab' => 'pending']) }}" style="text-decoration:none;">
                <div class="mini-stat amber">
                    <div class="mini-stat-body">
                        <i class="mdi mdi-clock-outline mini-stat-icon"></i>
                        <div class="mini-stat-label">Pending Requests</div>
                        <div class="mini-stat-value">{{ $stats['pending'] }}</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="mini-stat green">
                <div class="mini-stat-body">
                    <i class="mdi mdi-calendar-check mini-stat-icon"></i>
                    <div class="mini-stat-label">Approved This Week</div>
                    <div class="mini-stat-value">{{ $stats['approved_week'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-3">
            <div class="mini-stat purple">
                <div class="mini-stat-body">
                    <i class="mdi mdi-account-group mini-stat-icon"></i>
                    <div class="mini-stat-label">Staff Users</div>
                    <div class="mini-stat-value">{{ $stats['staff_count'] }}</div>
                </div>
            </div>
        </div>

    </div>

    {{-- ---------- Two-column preview ---------- --}}
    <div class="row">

        {{-- Pending queue preview --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="card-title mb-1">Pending Requests</h4>
                        </div>
                        <a href="{{ route('admin.bookings.manage', ['tab' => 'pending']) }}"
                           class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>

                    @if ($pendingPreview->isEmpty())
                        <div class="dashboard-empty">
                            <i class="mdi mdi-check-circle-outline"></i>
                            No pending requests.
                        </div>
                    @else
                        @foreach ($pendingPreview as $booking)
                            <div class="preview-row">
                                <div class="preview-main">
                                    <p class="preview-title">
                                        {{ $booking->facility->name ?? '—' }}
                                    </p>
                                    <span class="preview-meta">
                                        {{ $booking->user->name ?? '—' }}
                                        @if ($booking->facility?->category)
                                            · {{ $booking->facility->category->name }}
                                        @endif
                                    </span>
                                </div>
                                <div class="preview-dates">
                                    {{ $booking->date_range }}
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- This week's bookings --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="card-title mb-1">This Week</h4>
                            <p class="card-title-desc mb-0">
                                {{ $weekStart->format('d M') }} — {{ $weekEnd->format('d M Y') }}
                            </p>
                        </div>
                        <a href="{{ route('calendar.index') }}"
                           class="btn btn-sm btn-outline-primary">
                            View Calendar
                        </a>
                    </div>

                    @if ($weekBookings->isEmpty())
                        <div class="dashboard-empty">
                            <i class="mdi mdi-calendar-blank-outline"></i>
                            No approved bookings this week.
                        </div>
                    @else
                        @foreach ($weekBookings as $booking)
                            <div class="preview-row">
                                <div class="preview-main">
                                    <p class="preview-title">
                                        {{ $booking->facility->name ?? '—' }}
                                    </p>
                                    <span class="preview-meta">
                                        {{ $booking->user->name ?? '—' }}
                                        @if ($booking->start_time && $booking->end_time)
                                            · {{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }}–{{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }}
                                        @endif
                                    </span>
                                </div>
                                <div class="preview-dates">
                                    {{ $booking->date_range }}
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

    </div>

@endsection
