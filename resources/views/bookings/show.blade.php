@extends('layouts.app')
@section('title', 'Booking #'.$booking->id)
@include('partials.admin-ui')
@section('content')
<x-page-header>
        <x-slot:title>Booking #{{ $booking->id }}</x-slot:title>
        <x-slot:context>{{ $booking->facility?->name }} · {{ ucfirst($booking->status) }}</x-slot:context>
        <x-slot:actions>
            <a class="btn-icon btn-icon-outline" href="{{ auth()->user()->isAdmin() ? route('admin.bookings.index') : route('staff.my-bookings') }}">Back to bookings</a>
        </x-slot:actions>
    </x-page-header>
<div class="card"><div class="card-body"><dl class="row">
<dt class="col-sm-3">Requested by</dt><dd class="col-sm-9">{{ $booking->user?->name }}</dd>
@if ($booking->recurring_series_id)
<dt class="col-sm-3">Recurring series</dt><dd class="col-sm-9"><a href="{{ route('recurring.show', $booking->recurring_series_id) }}">View series #{{ $booking->recurring_series_id }}</a></dd>
@endif
<dt class="col-sm-3">Dates</dt><dd class="col-sm-9">{{ $booking->date_range }}</dd>
<dt class="col-sm-3">Time</dt><dd class="col-sm-9">{{ $booking->start_time ? substr($booking->start_time,0,5).' – '.substr($booking->end_time,0,5) : 'All day' }}</dd>
<dt class="col-sm-3">Purpose</dt><dd class="col-sm-9">{{ $booking->purpose ?: 'Not provided' }}</dd>
<dt class="col-sm-3">Admin remarks</dt><dd class="col-sm-9">{{ $booking->admin_remarks ?: 'None' }}</dd>
</dl></div></div>
@if(!auth()->user()->isAdmin() && in_array($booking->status, ['pending', 'approved'], true) && $booking->starts_at->isFuture() && !$booking->rescheduleRequests->contains('status', 'pending'))
    <p class="mt-3"><a class="btn btn-outline-primary" href="{{ route('staff.bookings.reschedule.create', $booking) }}">Request a new date/time</a></p>
@endif
@if(auth()->user()->isAdmin())
<p class="mt-3"><a class="btn btn-outline-primary" href="{{ route('admin.bookings.edit-status', $booking) }}">Edit booking status</a></p>
@endif
<div class="card mt-3"><div class="card-body"><h2 class="h5">Rescheduling requests</h2>
@forelse($booking->rescheduleRequests->sortByDesc('id') as $proposal)
    <div class="border-bottom py-3">
        <strong>#{{ $proposal->id }} · {{ ucfirst($proposal->status) }}</strong>
        <div>{{ $proposal->date_range }} · {{ $proposal->time_range }}</div>
        <small class="text-muted">Requested {{ $proposal->created_at->format('d M Y H:i') }}</small>
        @if($proposal->review_notes)<p class="mb-1">{{ $proposal->review_notes }}</p>@endif
        @if(auth()->user()->isAdmin() && $proposal->status === 'pending')
            <a class="btn btn-sm btn-outline-primary mt-2" href="{{ route('admin.reschedules.show', $proposal) }}">Review request</a>
        @elseif(!auth()->user()->isAdmin() && $proposal->status === 'pending')
            <form method="POST" action="{{ route('staff.reschedules.withdraw', $proposal) }}" class="mt-2">
                @csrf @method('DELETE')
                <button class="btn btn-sm btn-outline-danger" type="submit">Withdraw request</button>
            </form>
        @endif
    </div>
@empty
    <p class="text-muted mb-0">No rescheduling requests have been made.</p>
@endforelse
</div></div>
<div class="card mt-3"><div class="card-body"><h2 class="h5">Status history</h2>
<p class="text-muted">Times shown in {{ config('app.timezone') }}.</p>
<ol class="history-list">
@forelse($booking->events as $event)
<li class="mb-3"><strong>{{ ucfirst($event->new_status) }}</strong> · {{ $event->actor_name }}<br><small>{{ $event->created_at->format('d M Y, H:i') }}</small>@if($event->notes)<p>{{ $event->notes }}</p>@endif</li>
@empty<li>This booking predates status-history tracking. Current status: {{ $booking->status }}.</li>@endforelse
</ol></div></div>
@endsection
