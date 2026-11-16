@extends('layouts.app')

@section('title', 'Dashboard')

@include('dashboards._styles')

@section('content')

    <x-page-header>
        <x-slot:title>Dashboard</x-slot:title>
        <x-slot:actions><a href="{{ route('staff.bookings.create') }}" class="btn btn-primary">Book a Facility</a></x-slot:actions>
    </x-page-header>

    {{-- ---------- Stat cards ---------- --}}
    <div class="row">

        <div class="col-md-4">
            <a href="{{ route('staff.my-bookings') }}" style="text-decoration:none;">
                <div class="mini-stat amber">
                    <div class="mini-stat-body">
                        <i class="mdi mdi-clock-outline mini-stat-icon"></i>
                        <div class="mini-stat-label">Pending</div>
                        <div class="mini-stat-value">{{ $stats['pending'] }}</div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-md-4">
            <div class="mini-stat green">
                <div class="mini-stat-body">
                    <i class="mdi mdi-check-circle-outline mini-stat-icon"></i>
                    <div class="mini-stat-label">Approved · All Time</div>
                    <div class="mini-stat-value">{{ $stats['approved'] }}</div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="mini-stat teal">
                <div class="mini-stat-body">
                    <i class="mdi mdi-calendar-arrow-right mini-stat-icon"></i>
                    <div class="mini-stat-label">Upcoming</div>
                    <div class="mini-stat-value">{{ $stats['upcoming'] }}</div>
                </div>
            </div>
        </div>

    </div>

    {{-- ---------- Upcoming bookings + quick links ---------- --}}
    <div class="row">

        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="card-title mb-1">My Upcoming Bookings</h4>
                        </div>
                        <a href="{{ route('staff.my-bookings') }}"
                           class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>

                    @if ($upcomingBookings->isEmpty())
                        <div class="dashboard-empty">
                            <i class="mdi mdi-calendar-blank-outline"></i>
                            You have no upcoming bookings.
                            <div class="mt-3">
                                <a href="{{ route('staff.bookings.create') }}" class="btn btn-primary btn-sm">
                                    <i class="mdi mdi-plus"></i> Book a Facility
                                </a>
                            </div>
                        </div>
                    @else
                        @foreach ($upcomingBookings as $booking)
                            <div class="preview-row">
                                <div class="preview-main">
                                    <p class="preview-title">
                                        {{ $booking->facility->name ?? '—' }}
                                        <span class="badge {{ $booking->status_badge_class }} ml-1" style="font-size: 0.625rem;">
                                            {{ ucfirst($booking->status) }}
                                        </span>
                                    </p>
                                    <span class="preview-meta">
                                        {{ $booking->facility->category->name ?? '—' }}
                                        @if ($booking->purpose)
                                            · {{ \Illuminate\Support\Str::limit($booking->purpose, 40) }}
                                        @endif
                                    </span>
                                </div>
                                <div class="preview-dates">
                                    {{ $booking->date_range }}
                                    @if ($booking->start_time && $booking->end_time)
                                        <div>
                                            {{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }}
                                            – {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

    </div>

@endsection
