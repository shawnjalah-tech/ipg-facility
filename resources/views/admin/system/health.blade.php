@extends('layouts.app')

@section('title', 'System Health')

@section('content')
<div class="container-fluid" style="max-width:1100px;margin:0 auto;padding:1.5rem">
    <x-page-header><x-slot:title>System Health</x-slot:title></x-page-header>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Booking expiration scheduler</h2>
                @if ($expiration && $expiration['fresh'])
                    <p class="text-success">Healthy — successful run recently</p>
                @else
                    <p class="text-danger">Not confirmed — last successful run is missing or stale</p>
                @endif
                <p>Last success: {{ $expiration['at'] ?? 'Not recorded' }}</p>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Queue processing</h2>
                @if ($queue && $queue['fresh'])
                    <p class="text-success">Healthy — recent {{ $queueDriver === 'sync' ? 'synchronous' : 'worker' }} probe</p>
                @else
                    <p class="text-danger">Not confirmed — queue probe not processed recently</p>
                @endif
                <p>Last probe: {{ $queue['at'] ?? 'Not recorded' }}</p>
                <p>Driver: <strong>{{ $queueDriver }}</strong> · Waiting: <strong>{{ $queued ?? 'Unknown' }}</strong> · Failed: <strong>{{ $failed ?? 'Unknown' }}</strong></p>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Email delivery</h2>
                @if (in_array($mailDriver, ['log', 'array'], true))
                    <p class="text-warning">Not delivering email — test transport selected</p>
                @else
                    <p class="text-info">Transport configured — delivery still requires a real send test</p>
                @endif
                <p>Mailer: <strong>{{ $mailDriver }}</strong></p>
            </div></div>
        </div>
        <div class="col-md-6">
            <div class="card h-100"><div class="card-body">
                <h2 class="h5">Access and deployment</h2>
                <p>Public registration: <strong>{{ $registrationOpen ? 'Open' : 'Closed' }}</strong></p>
                <p>Production debug: <strong>{{ $productionDebug ? 'Unsafe — enabled' : 'Off' }}</strong></p>
            </div></div>
        </div>
    </div>
</div>
@endsection
