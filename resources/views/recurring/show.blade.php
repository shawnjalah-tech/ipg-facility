@extends('layouts.app')
@section('title', 'Recurring Series #'.$series->id)
@include('partials.admin-ui')
@section('content')
<x-page-header>
    <x-slot:title>Recurring Series #{{ $series->id }}</x-slot:title>
    <x-slot:context>{{ $series->facility?->name }} · {{ ucfirst($series->frequency) }} · {{ $series->occurrence_count }} occurrences</x-slot:context>
    <x-slot:actions><a class="btn-icon btn-icon-outline" href="{{ route('recurring.index') }}">All recurring series</a></x-slot:actions>
</x-page-header>
<div class="admin-card p-3 mb-3">
    <p class="mb-2"><strong>Purpose:</strong> {{ $series->bookings->first()?->purpose ?: 'Not provided' }}</p>
    <p class="mb-2"><strong>Staff:</strong> {{ $series->owner?->name }} · <strong>Created by:</strong> {{ $series->creator?->name }}</p>
    @if (auth()->user()->isAdmin() && $series->bookings->contains('status', 'pending'))
        <div class="d-flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.recurring.decide', $series) }}">
                @csrf
                <input type="hidden" name="decision" value="approved">
                <button type="submit" class="btn-icon btn-icon-primary" onclick="return confirm('Approve every pending occurrence? All dates will be checked again.');">Approve pending series</button>
            </form>
            <form method="POST" action="{{ route('admin.recurring.decide', $series) }}" class="d-flex flex-wrap gap-2">
                @csrf
                <input type="hidden" name="decision" value="rejected">
                <label for="series-reason" class="visually-hidden">Rejection reason</label>
                <input id="series-reason" class="form-control" required minlength="5" maxlength="1000" name="reason" placeholder="Reason for rejection" aria-label="Rejection reason">
                <button type="submit" class="btn-icon btn-icon-outline" onclick="return confirm('Reject every pending occurrence?');">Reject pending series</button>
            </form>
        </div>
    @endif
</div>
<div class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Time</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @foreach ($series->bookings as $booking)
                    <tr>
                        <td>{{ $booking->date_range }} · #{{ $booking->id }}</td>
                        <td>{{ $booking->start_time ? substr($booking->start_time,0,5).' – '.substr($booking->end_time,0,5) : 'All day' }}</td>
                        <td><span class="badge {{ $booking->status_badge_class }}">{{ ucfirst($booking->status) }}</span></td>
                        <td>
                            <a class="btn-icon btn-icon-outline" href="{{ route('bookings.show', $booking) }}">View</a>
                            @if (!auth()->user()->isAdmin() && in_array($booking->status, ['pending', 'approved'], true) && $booking->starts_at->isFuture())
                                <a class="btn-icon btn-icon-outline" href="{{ route('staff.bookings.reschedule.create', $booking) }}">Reschedule</a>
                                <form method="POST" action="{{ route('staff.bookings.cancel', $booking) }}" class="d-inline">
                                    @csrf @method('PUT')
                                    <button type="submit" class="btn-icon btn-icon-outline" onclick="return confirm('Cancel only this occurrence?');">Cancel this date</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
