@extends('layouts.app')
@section('title', 'Facility Availability')
@push('styles')<link rel="stylesheet" href="{{ asset('css/facility-calendar.css') }}">@endpush
@section('content')
<x-page-header>
    <x-slot:title>Facility Availability</x-slot:title>
    <x-slot:actions>
        @if($rescheduleBooking)
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('staff.bookings.reschedule.create', $rescheduleBooking) }}">Back to rescheduling #{{ $rescheduleBooking->id }}</a>
        @else
            <a class="btn btn-outline-primary btn-sm" href="{{ auth()->user()->isAdmin() ? route('admin.bookings.create') : route('staff.bookings.create') }}">New booking</a>
        @endif
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.index', array_filter(['view' => 'slots', 'week' => $weekStart->toDateString(), 'facility' => $facilities->first()?->id, 'reschedule' => $rescheduleBooking?->id])) }}">Detailed slots</a>
    </x-slot:actions>
</x-page-header>

<div class="fb-shell">
    <div class="fb-toolbar">
        @php
            $nav = array_filter(['category' => $categoryId, 'facility' => request('facility'), 'reschedule' => $rescheduleBooking?->id, 'duration' => $duration]);
        @endphp
        <nav class="fb-week-nav" aria-label="Change week">
            <a aria-label="Previous week" href="{{ route('calendar.index', array_merge($nav, ['week' => $weekStart->copy()->subWeek()->toDateString()])) }}">&#8592;</a>
            <span class="fb-week-label">{{ $weekStart->format('d M') }} – {{ $weekEnd->format('d M Y') }}</span>
            <a aria-label="Next week" href="{{ route('calendar.index', array_merge($nav, ['week' => $weekStart->copy()->addWeek()->toDateString()])) }}">&#8594;</a>
            <a class="fb-today" href="{{ route('calendar.index', $nav) }}">Today</a>
        </nav>
        <form method="GET" action="{{ route('calendar.index') }}" class="fb-filters">
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
            @if($rescheduleBooking)<input type="hidden" name="reschedule" value="{{ $rescheduleBooking->id }}">@endif
            <label for="fb-category">Category</label>
            <select name="category" id="fb-category" class="form-control form-control-sm" @disabled((bool) $rescheduleBooking)>
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-primary btn-sm" type="submit" @disabled((bool) $rescheduleBooking)>Filter</button>
            @if(!$rescheduleBooking && ($categoryId || request('facility')))<a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.index', ['week' => $weekStart->toDateString()]) }}">Clear</a>@endif
        </form>
        <label class="fb-search-wrap" for="fb-search"><span class="sr-only">Find facility in displayed categories</span><input type="search" id="fb-search" class="form-control form-control-sm" placeholder="Find facility…" autocomplete="off"></label>
    </div>

    <div class="fb-board-heading">
        <h2>WEEKLY OVERVIEW</h2>
        <div class="fb-legend" aria-label="Availability legend">
            <span><i class="fb-dot fb-dot-free" aria-hidden="true"></i> Available</span>
            <span><i class="fb-dot fb-dot-booked" aria-hidden="true"></i> Approved</span>
            <span><i class="fb-dot fb-dot-pending" aria-hidden="true"></i> Pending</span>
            <span><i class="fb-dot fb-dot-closed" aria-hidden="true"></i> Closed</span>
        </div>
    </div>

    @if($groups->isEmpty())
        <div class="fb-empty" role="status">No facilities match this filter.</div>
    @else
        <div class="fb-groups" id="fb-groups">
            @foreach($groups as $group)
                <details class="fb-category" @if($loop->first || $categoryId || request('facility') || $rescheduleBooking) open @endif>
                    <summary class="fb-category-title">
                        <span class="fb-category-toggle" aria-hidden="true">&#8250;</span>
                        <span class="fb-category-name">{{ $group['category']->name }}</span>
                        <span class="fb-category-count">{{ $group['facilities']->count() }} {{ $group['facilities']->count() === 1 ? 'facility' : 'facilities' }}</span>
                    </summary>
                    <div class="fb-table-scroll" role="region" aria-label="{{ $group['category']->name }} weekly availability" tabindex="0">
                        <table class="fb-board">
                            <thead>
                                <tr>
                                    <th scope="col" class="fb-facility-col">Facility</th>
                                    @foreach($days as $day)
                                        <th scope="col" class="{{ $day->isToday() ? 'fb-is-today' : '' }}">
                                            <span class="fb-dayname">{{ $day->format('D') }}</span>
                                            <span class="fb-daydate">{{ $day->format('d M') }}</span>
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($group['facilities'] as $facility)
                                    <tr class="fb-facility-row" data-facility-name="{{ strtolower($facility->name) }}">
                                        <th scope="row" class="fb-facility-col">
                                            <span class="fb-room-name">{{ $facility->name }}</span>
                                            @if($facility->capacity)<span class="fb-room-capacity">{{ $facility->capacity }} seats</span>@endif
                                        </th>
                                        @foreach($days as $day)
                                            @php
                                                $cell = $board[$facility->id][$day->toDateString()];
                                                $bookings = $cell['bookings'];
                                                $closures = $cell['closures'];
                                                $dayRoute = route('calendar.day', array_filter(['facility' => $facility->id, 'date' => $day->toDateString(), 'reschedule' => $rescheduleBooking?->id]));
                                                $bookRoute = $rescheduleBooking
                                                    ? route('staff.bookings.reschedule.create', ['booking' => $rescheduleBooking->id, 'start_date' => $day->toDateString(), 'end_date' => $day->toDateString()])
                                                    : (auth()->user()->isAdmin()
                                                        ? route('admin.bookings.create', ['facility_id' => $facility->id, 'start_date' => $day->toDateString(), 'end_date' => $day->toDateString()])
                                                        : route('staff.bookings.create', ['facility_id' => $facility->id, 'start_date' => $day->toDateString(), 'end_date' => $day->toDateString()]));
                                            @endphp
                                            <td class="fb-day-cell {{ $cell['fully_closed'] ? 'fb-is-closed' : ($bookings->isNotEmpty() ? 'fb-is-booked' : ($closures->isNotEmpty() ? 'fb-is-limited' : 'fb-is-free')) }} {{ $cell['past'] ? 'fb-is-past' : '' }}">
                                                <div class="fb-cell-content">
                                                    @if($cell['fully_closed'])
                                                        <a class="fb-state fb-state-closed" data-day-detail href="{{ $dayRoute }}"><span aria-hidden="true">&#128274;</span> Closed</a>
                                                    @elseif($closures->isNotEmpty())
                                                        <a class="fb-state fb-state-closed" data-day-detail href="{{ $dayRoute }}">Limited hours</a>
                                                    @endif
                                                    @foreach($bookings->take(2) as $booking)
                                                        @php
                                                            $starts = $booking->starts_at->lt($day) ? $day : $booking->starts_at;
                                                            $ends = $booking->ends_at->gt($day->copy()->addDay()) ? $day->copy()->addDay() : $booking->ends_at;
                                                        @endphp
                                                        <a class="fb-person fb-person-{{ $booking->status }}" href="{{ $dayRoute }}#fb-booking-{{ $booking->id }}" data-day-detail data-booking-id="{{ $booking->id }}" aria-label="View booking details for {{ $booking->user?->name ?? 'Unknown staff' }} on {{ $day->format('d M Y') }}" title="View {{ $booking->user?->name ?? 'Unknown staff' }}'s booking purpose and schedule">
                                                            <span class="fb-person-name">{{ $booking->user?->name ?? 'Unknown staff' }}</span>
                                                            <span class="fb-person-time">{{ $booking->start_time && $booking->end_time ? $starts->format('H:i').'–'.$ends->format('H:i') : 'All day' }}</span>
                                                        </a>
                                                    @endforeach
                                                    @if($bookings->count() > 2)
                                                        <a class="fb-more" data-day-detail href="{{ $dayRoute }}">+{{ $bookings->count() - 2 }} more</a>
                                                    @endif
                                                    @if($cell['can_book'])
                                                        <a class="fb-book" href="{{ $bookRoute }}" aria-label="Book {{ $facility->name }} on {{ $day->format('d M Y') }}">+ Book</a>
                                                    @elseif($bookings->isEmpty() && $closures->isEmpty() && $cell['past'])
                                                        <span class="fb-past-label">Past</span>
                                                    @endif
                                                </div>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endforeach
        </div>
        <div class="fb-no-search" id="fb-no-search" hidden>No facilities match your search in the displayed categories.</div>
    @endif
</div>

<dialog class="fb-dialog" id="fb-day-dialog" aria-label="Daily availability details">
    <div class="fb-dialog-toolbar"><h2>Day details</h2><button type="button" id="fb-dialog-close" class="btn btn-outline-secondary btn-sm" aria-label="Close details">Close &#10005;</button></div>
    <div id="fb-dialog-body" aria-live="polite"></div>
</dialog>
@push('scripts')<script src="{{ asset('js/facility-calendar.js') }}" defer></script>@endpush
@endsection
