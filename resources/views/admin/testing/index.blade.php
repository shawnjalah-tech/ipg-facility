@extends('layouts.app')
@section('title', 'Load Test Lab')
@section('page-title', 'Load Test Lab')
@section('content')
<div class="admin-page-header">
    <div><h1 class="admin-page-title">50-User Simulation Lab</h1>
        <p class="admin-page-sub">Real booking records in the isolated <strong>loadtest.sqlite</strong> database. Refresh to see activity.</p>
    </div>
    <a href="{{ route('admin.load-test.index') }}" class="btn-icon btn-icon-outline">Refresh results</a>
</div>
<div class="alert alert-warning"><strong>Test environment only.</strong> These are simulated users and bookings, not real IPG records. The dashboard does not start the test; run Locust in your terminal, then refresh here.</div>
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Simulated accounts</small><h3>{{ array_sum($sessions) }} / 50</h3><div class="text-muted small">{{ $sessions['booker'] }} booking · {{ $sessions['viewer'] }} browsing · {{ $sessions['admin'] }} admins</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Created bookings</small><h3>{{ array_sum($counts) }}</h3><div class="text-muted small">Includes pending and decided</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Rescheduling requests</small><h3>{{ array_sum($proposals) }}</h3><div class="text-muted small">{{ $proposals['pending'] ?? 0 }} still pending</div></div></div></div>
    <div class="col-md-3"><div class="card"><div class="card-body"><small>Integrity checks</small><h3>{{ ($conflicts || $missingEvents) ? 'Investigate' : 'No issue found' }}</h3><div class="text-muted small">{{ $conflicts }} overlapping approved pairs · {{ $missingEvents }} bookings without events</div></div></div></div>
</div>
<div class="card mb-4"><div class="card-body">
    <h5 class="mb-3">Booking lifecycle outcomes</h5>
    <div class="row g-3">
    @foreach ($counts as $status => $number)
        <div class="col"><div class="border rounded p-3 text-center"><div class="text-muted small">{{ ucfirst($status) }}</div><h3 class="my-1">{{ $number }}</h3></div></div>
    @endforeach
    </div>
</div></div>
<div class="card mb-4"><div class="card-body">
    <h5 class="mb-3">HTTP load-test measurements</h5>
    @if ($stats)
        <div class="row g-3">
          <div class="col-md-3"><strong>{{ $stats['Request Count'] ?? '—' }}</strong><div class="small text-muted">Requests</div></div>
          <div class="col-md-3"><strong>{{ $stats['Failure Count'] ?? '—' }}</strong><div class="small text-muted">Failures</div></div>
          <div class="col-md-3"><strong>{{ isset($stats['95%']) ? round((float)$stats['95%']) . ' ms' : '—' }}</strong><div class="small text-muted">95th percentile</div></div>
          <div class="col-md-3"><strong>{{ isset($stats['Requests/s']) ? round((float)$stats['Requests/s'], 2) : '—' }}</strong><div class="small text-muted">Requests / second</div></div>
        </div>
    @else
        <p class="text-muted mb-0">No Locust CSV report yet. Start the simulator from a terminal; reports will appear here after stats are written.</p>
    @endif
</div></div>
<div class="card mb-4"><div class="card-body">
    <h5 class="mb-3">Latest simulated bookings</h5>
    <div class="table-responsive"><table class="table table-striped table-sm"><thead><tr><th>ID</th><th>Account</th><th>Facility</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>@forelse ($recent as $booking)
       <tr><td><a href="{{ route('bookings.show', $booking) }}">#{{ $booking->id }}</a></td><td>{{ $booking->user?->name }}</td><td>{{ $booking->facility?->name }}</td><td>{{ $booking->date_range }}</td><td>{{ ucfirst($booking->status) }}</td></tr>
    @empty <tr><td colspan="5">No simulated bookings yet.</td></tr>@endforelse
    </tbody></table></div>
</div></div>
@if ($failures)
<div class="card"><div class="card-body"><h5>Recent HTTP failure types</h5><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Request</th><th>Error</th><th>Occurrences</th></tr></thead><tbody>
@foreach ($failures as $failure)<tr><td>{{ $failure['Name'] ?? '—' }}</td><td style="max-width:500px;word-break:break-word">{{ $failure['Error'] ?? '—' }}</td><td>{{ $failure['Occurrences'] ?? '—' }}</td></tr>@endforeach
</tbody></table></div></div></div>
@endif
@endsection
