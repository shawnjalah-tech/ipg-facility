@extends('layouts.app')
@section('title', 'Review Rescheduling Request')
@include('partials.admin-ui')
@section('content')
<x-page-header>
        <x-slot:title>Rescheduling request #{{ $reschedule->id }}</x-slot:title>
        <x-slot:context>Booking <a href="{{ route('bookings.show', $reschedule->booking_id) }}">#{{ $reschedule->booking_id }}</a> · {{ $reschedule->booking?->facility?->name }} · {{ $reschedule->requester?->name }}</x-slot:context>
        <x-slot:actions>
            <a href="{{ route('admin.bookings.manage', ['tab' => 'reschedules']) }}" class="btn-icon btn-icon-outline">Back to queue</a>
        </x-slot:actions>
    </x-page-header>
<div class="row"><div class="col-lg-8">
<div class="card schedule-context"><div class="card-body"><h5>Current schedule</h5><p>{{ $reschedule->booking?->date_range }}<br>{{ $reschedule->booking?->start_time ? substr($reschedule->booking->start_time,0,5).' – '.substr($reschedule->booking->end_time,0,5) : 'All day' }}<br><small>Current status: {{ ucfirst($reschedule->booking?->status) }}</small></p>
</div><div class="card-body"><h5>Proposed schedule</h5><p><strong>{{ $reschedule->date_range }}</strong><br>{{ $reschedule->time_range }}</p>
<p>Requested {{ $reschedule->created_at->format('d M Y, H:i') }}</p></div></div>
@if($reschedule->status === 'pending')
    <div class="card mt-3"><div class="card-body"><h5>Admin decision</h5>
        <form method="POST" action="{{ route('admin.reschedules.decide', $reschedule) }}">
            @csrf @method('PUT')
            <label for="review_notes">Decision notes (shared with the booker)</label>
            <textarea id="review_notes" name="review_notes" rows="3" minlength="5" maxlength="1000" class="form-control mb-3">{{ old('review_notes') }}</textarea>
            <div class="d-flex flex-wrap" style="gap:10px">
                <button type="submit" class="btn btn-success" name="decision" value="approve">Approve new schedule</button>
                <button type="submit" class="btn btn-danger" name="decision" value="reject">Reject change</button>
            </div>
        </form>
    </div></div>
@else
    <div class="alert alert-info mt-3">This request was {{ $reschedule->status }}. {{ $reschedule->review_notes }}</div>
@endif
</div></div>
@endsection
