@extends('layouts.app')
@section('title', 'Facility Availability')
@push('styles')
<style>
    .availability-controls {display:flex;gap:12px;flex-wrap:wrap;align-items:end;margin:18px 0;}
    .availability-controls label {display:block;font-weight:600;font-size:.8rem;margin-bottom:5px;color:#343a40;}
    .availability-controls select {min-width:200px;max-width:100%;}
    .availability-legend {display:flex;flex-wrap:wrap;gap:15px;margin:12px 0 18px;font-size:.83rem;}
    .availability-legend span::before {content:"";display:inline-block;width:12px;height:12px;vertical-align:middle;margin-right:6px;border-radius:3px;background:var(--legend-bg);border:1px solid var(--legend-border);}
    .availability-grid-wrapper {overflow-x:auto;max-height:68vh;overflow-y:auto;border:1px solid #e4e9ec;border-radius:10px;background:#fff;}
    .availability-grid {width:100%;min-width:760px;border-collapse:separate;border-spacing:0;table-layout:fixed;}
    .availability-grid th,.availability-grid td {border-right:1px solid #e8edef;border-bottom:1px solid #e8edef;padding:3px;text-align:center;}
    .availability-grid thead th {position:sticky;top:0;z-index:2;background:#f3f9f9;padding:10px 3px;color:#343a40;font-size:.8rem;}
    .availability-grid thead th:first-child {width:75px;left:0;z-index:4;}
    .availability-grid tbody th {position:sticky;left:0;z-index:1;background:#f9fafb;font-size:.78rem;color:#626d73;padding:10px 3px;white-space:nowrap;}
    .availability-grid .today {background:#e0f6f6;}
    .availability-slot {display:flex;align-items:center;justify-content:center;min-height:37px;padding:5px 2px;border-radius:6px;font-size:.71rem;line-height:1.2;text-decoration:none!important;font-weight:600;}
    .availability-slot.available {background:#e7f7ed;color:#176b41;box-shadow:inset 0 0 0 1px #b9e7cd;}
    a.availability-slot.available:hover,a.availability-slot.available:focus {background:#bdebd0;color:#0d4f2d;outline:2px solid #15834e;outline-offset:-2px;}
    .availability-slot.approved {background:#fbe3e8;color:#a43246;}
    .availability-slot.pending {background:#fff0d1;color:#865a11;}
    .availability-slot.closed {background:#ece8fb;color:#61469a;}
    .availability-slot.unavailable {background:#f2f3f5;color:#6b7785;}
    .availability-slot.past {background:#f2f3f5;color:#9aa0a6;font-weight:400;}
    .availability-actions {display:flex;flex-wrap:wrap;gap:6px;align-items:center;justify-content:space-between;}

</style>
@endpush

@section('content')
<x-page-header>
    <x-slot:title>Facility Availability</x-slot:title>
    <x-slot:actions>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.index', array_filter(['week' => $weekStart->toDateString(), 'facility' => $facility?->id, 'reschedule' => $rescheduleBooking?->id])) }}">&larr; Weekly overview</a>
        @if($rescheduleBooking)
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('staff.bookings.reschedule.create', $rescheduleBooking) }}">Back to rescheduling #{{ $rescheduleBooking->id }}</a>
        @elseif(!auth()->user()->isAdmin())
            <a class="btn btn-outline-primary btn-sm" href="{{ auth()->user()->isAdmin() ? route('admin.bookings.create') : route('staff.bookings.create') }}">Booking form</a>
        @endif
    </x-slot:actions>
</x-page-header>
<div class="card"><div class="card-body">

    <form class="availability-controls" method="GET" action="{{ route('calendar.index') }}">
        <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
        <input type="hidden" name="view" value="slots">
        @if($rescheduleBooking)<input type="hidden" name="reschedule" value="{{ $rescheduleBooking->id }}">@endif
        @unless($rescheduleBooking)
        <div><label for="category-filter">Category</label>
            <select id="category-filter" name="category" class="form-control" >
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        @endunless
        <div><label for="facility-filter">Facility</label>
            <select id="facility-filter" name="facility" class="form-control" >
                @forelse($facilities as $f)
                    <option value="{{ $f->id }}" @selected($facility?->id === $f->id)>{{ $f->name }} ({{ $f->category?->name }})</option>
                @empty<option value="">No active facilities</option>@endforelse
            </select>
        </div>
        <div><label for="slot-filter">Grid interval</label><select id="slot-filter" name="slot" class="form-control"><option value="30" @selected($slotMinutes === 30)>30 minutes</option><option value="60" @selected($slotMinutes === 60)>1 hour</option></select></div>
        <div><label for="duration-filter">Find free for</label><select id="duration-filter" name="duration" class="form-control">@foreach([30 => '30 minutes', 60 => '1 hour', 120 => '2 hours', 180 => '3 hours'] as $n => $label)<option value="{{ $n }}" @selected($duration === $n)>{{ $label }}</option>@endforeach</select></div>
        <button type="submit" class="btn btn-primary">Show availability</button>
    </form>

    @php
        $nav = array_filter(['view' => 'slots', 'category' => $categoryId, 'facility' => $facility?->id, 'reschedule' => $rescheduleBooking?->id, 'slot' => $slotMinutes, 'duration' => $duration]);
    @endphp
    <div class="availability-actions mb-3">
        <div class="font-weight-bold">{{ $weekStart->format('d M Y') }} – {{ $weekEnd->format('d M Y') }} <small class="text-muted">({{ config('app.timezone') }})</small></div>
        <div class="d-flex flex-wrap" style="gap:6px">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.index', array_merge($nav, ['week' => $weekStart->copy()->subWeek()->toDateString()])) }}">&larr; Previous</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.index', $nav) }}">This week</a>
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('calendar.index', array_merge($nav, ['week' => $weekStart->copy()->addWeek()->toDateString()])) }}">Next &rarr;</a>
        </div>
    </div>

    <div class="availability-legend">
        <span style="--legend-bg:#e7f7ed;--legend-border:#b9e7cd">Available</span>
        <span style="--legend-bg:#fbe3e8;--legend-border:#efbdc6">Booked</span>
        <span style="--legend-bg:#fff0d1;--legend-border:#ecd493">Pending</span>
        <span style="--legend-bg:#ece8fb;--legend-border:#d8cff2">Closed</span>
        <span style="--legend-bg:#f2f3f5;--legend-border:#e4e4e4">Past / Not free for selected duration</span>
    </div>
    @if(! $facility)
        <div class="alert alert-info">No active facilities found for this category.</div>
    @else
        <div class="availability-grid-wrapper" role="region" aria-label="Weekly facility time slots" tabindex="0">
            <table class="availability-grid">
                <thead><tr><th scope="col">Time<br><small>Same date</small></th>
                    @foreach($days as $day)
                        <th scope="col" class="{{ $day->isToday() ? 'today' : '' }}">{{ $day->format('D') }}<br>{{ $day->format('d M') }}</th>
                    @endforeach
                </tr></thead>
                <tbody>
                @foreach($hours as $hour)
                    @foreach($minutes as $minute)
                    @php
                        $key = sprintf('%02d:%02d', $hour, $minute);
                    @endphp
                    <tr><th scope="row">{{ $key }}</th>
                        @foreach($days as $day)
                            @php
                                $type = $weekSlots[$day->toDateString()][$key];
                                $slotBooking = $slotBookings[$day->toDateString()][$key] ?? null;
                                $slotStart = $day->copy()->startOfDay()->addHours($hour)->addMinutes($minute);
                                $slotEnd = $slotStart->copy()->addMinutes($duration);
                                $query = ['start_date' => $slotStart->toDateString(), 'end_date' => $slotEnd->toDateString(), 'start_time' => $slotStart->format('H:i'), 'end_time' => $slotEnd->format('H:i')];
                                $destination = $rescheduleBooking
                                    ? route('staff.bookings.reschedule.create', array_merge(['booking' => $rescheduleBooking->id], $query))
                                    : (auth()->user()->isAdmin()
                                        ? route('admin.bookings.create', array_merge(['facility_id' => $facility->id], $query))
                                        : route('staff.bookings.create', array_merge(['facility_id' => $facility->id], $query)));
                                $title = match($type) {'approved' => 'Already approved', 'pending' => 'Request awaiting admin review', 'closed' => 'Facility closed', 'unavailable' => 'Not available for requested duration', 'past' => 'Time has passed', default => 'Available for a booking request'};
                            @endphp
                            <td>
                                @if($type === 'available')
                                    <a class="availability-slot available" href="{{ $destination }}" title="{{ $day->format('d M') }} {{ $key }}: {{ $title }}" aria-label="Select {{ $day->format('d M') }} {{ $key }} for {{ $duration }} minutes">Book</a>
                                @elseif(auth()->user()->isAdmin() && $slotBooking)
                                    <a class="availability-slot {{ $type }}" href="{{ route('bookings.show', $slotBooking) }}" aria-label="{{ ucfirst($type) }} booking #{{ $slotBooking->id }}, {{ $day->format('d M') }} {{ $key }}">#{{ $slotBooking->id }} · {{ ucfirst($type) }}</a>
                                @elseif($slotBooking)
                                    <a class="availability-slot {{ $type }}" href="{{ route('calendar.day', array_filter(['facility' => $facility->id, 'date' => $day->toDateString(), 'reschedule' => $rescheduleBooking?->id])) }}" title="View booking name and purpose">{{ $slotBooking->user?->name ?? 'Unknown staff' }} · {{ $type === 'approved' ? 'Booked' : 'Pending' }}</a>
                                @else
                                    <span class="availability-slot {{ $type }}" title="{{ $title }}">{{ match($type) {'approved' => 'Booked', 'pending' => 'Pending', 'closed' => 'Closed', 'unavailable' => 'Busy', 'past' => '—', default => 'Free'} }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    @endforeach
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div></div>
@endsection
