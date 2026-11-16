@extends('layouts.app')

@section('title', 'Edit booking status')

@include('partials.admin-ui')

@section('content')
<x-page-header>
        <x-slot:title>Edit booking #{{ $booking->id }}</x-slot:title>
        <x-slot:context>{{ $booking->facility?->name }} · {{ $booking->user?->name }} · {{ $booking->date_range }}</x-slot:context>
        <x-slot:actions>
            <a class="btn-icon btn-icon-outline" href="{{ route('admin.bookings.index') }}">Back to Booking History</a>
        </x-slot:actions>
    </x-page-header>

<div class="card mb-4">
    <div class="card-body">
        <p><strong>Current status:</strong> {{ ucfirst($booking->status) }}</p>
        <p><strong>Booking start:</strong> {{ $booking->starts_at->format('d M Y, H:i') }} ({{ config('app.timezone') }})</p>


        <form action="{{ route('admin.bookings.update-status', $booking) }}" method="POST">
            @csrf
            @method('PUT')
            <input type="hidden" name="expected_status" value="{{ $booking->status }}">
            <div class="admin-field mb-3">
                <label for="status">Change status to</label>
                <select class="admin-select" id="status" name="status" required>
                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'expired' => 'Expired'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $booking->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-field mb-3">
                <label for="admin_remarks">Reason for correction (shared with the booker)</label>
                <textarea class="admin-textarea" name="admin_remarks" id="admin_remarks" rows="3" minlength="5" maxlength="1000" required>{{ old('admin_remarks') }}</textarea>

            </div>
            <button class="btn btn-primary" type="submit">Save status correction</button>
            <a class="btn btn-outline-secondary" href="{{ route('admin.bookings.index') }}">Cancel</a>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <h2 class="h5">Full status history</h2>
        <ol class="history-list">
            @forelse ($booking->events as $event)
                <li class="mb-3">
                    <strong>{{ ucfirst($event->new_status) }}</strong>
                    @if ($event->old_status) (from {{ ucfirst($event->old_status) }}) @endif
                    — {{ $event->actor_name }}
                    <div class="text-muted"><small>{{ $event->created_at->format('d M Y, H:i') }}</small></div>
                    @if ($event->notes)<p>{{ $event->notes }}</p>@endif
                </li>
            @empty
                <li>This booking predates event history. Its current status is {{ $booking->status }}.</li>
            @endforelse
        </ol>
    </div>
</div>
@endsection
