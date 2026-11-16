@extends('layouts.app')
@section('title', 'Request Booking Reschedule')
@include('partials.admin-ui')
@section('content')
<x-page-header>
        <x-slot:title>Reschedule booking #{{ $booking->id }}</x-slot:title>
        <x-slot:context>{{ $booking->facility?->name }} · Current status: {{ ucfirst($booking->status) }}</x-slot:context>
        <x-slot:actions>
            <a class="btn-icon btn-icon-outline" href="{{ route('bookings.show', $booking) }}">Back to booking</a>
        </x-slot:actions>
    </x-page-header>

@if(!in_array($booking->status, ['pending','approved'], true) || $booking->starts_at->lte(now()))
    <div class="alert alert-warning">This booking is no longer eligible for rescheduling. Only upcoming pending or approved bookings can be changed.</div>
@elseif($booking->rescheduleRequests->contains('status', 'pending'))
    <div class="alert alert-info">You already have a rescheduling request awaiting admin review. You can withdraw it from your booking details.</div>
@else
    <div class="row"><div class="col-lg-8">
        <div class="admin-card p-4">
            <p><strong>Current dates:</strong> {{ $booking->date_range }}<br>
               <strong>Current time:</strong> {{ $booking->start_time ? substr($booking->start_time,0,5).' – '.substr($booking->end_time,0,5) : 'All day' }}</p>
            <h2 class="h5">Proposed schedule</h2>
            <form method="POST" action="{{ route('staff.bookings.reschedule.store', $booking) }}" class="admin-form">
                @csrf
                <div class="admin-form-grid two-col">
                    <div class="admin-field"><label for="start_date">New start date<span class="req">*</span></label>
                        <input class="admin-input" type="date" id="start_date" name="start_date" min="{{ today()->toDateString() }}" value="{{ old('start_date', request()->query('start_date', $booking->start_date->toDateString())) }}" required></div>
                    <div class="admin-field"><label for="end_date">New end date<span class="req">*</span></label>
                        <input class="admin-input" type="date" id="end_date" name="end_date" min="{{ today()->toDateString() }}" value="{{ old('end_date', request()->query('end_date', $booking->end_date->toDateString())) }}" required></div>
                </div>
                <x-booking-time-fields
                    :start-time="old('start_time', request()->query('start_time', $booking->start_time ? substr($booking->start_time, 0, 5) : ''))"
                    :end-time="old('end_time', request()->query('end_time', $booking->end_time ? substr($booking->end_time, 0, 5) : ''))" />
                <div class="d-flex flex-wrap mt-4" style="gap:10px">
                    <button class="btn-icon btn-icon-primary" type="submit">Submit reschedule request</button>
                    <a class="btn-icon btn-icon-outline" href="{{ route('calendar.index', ['reschedule' => $booking->id, 'week' => $booking->start_date->toDateString()]) }}">View available time slots</a>
                </div>
            </form>
        </div>
    </div></div>
@endif
@endsection

@push('scripts')
<script src="{{ asset('js/booking-schedule.js') }}"></script>
@endpush
