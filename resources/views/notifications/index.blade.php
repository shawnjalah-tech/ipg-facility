@extends('layouts.app')
@section('title', 'Notifications')
@section('content')
<x-page-header>
    <x-slot:title>Notifications</x-slot:title>
    <x-slot:actions>
        <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
            <button type="submit" class="btn btn-outline-primary">Mark all as read</button>
        </form>
    </x-slot:actions>
</x-page-header>
<div class="card"><div class="card-body">
    @forelse($notices as $notice)
        <div class="d-flex flex-wrap justify-content-between align-items-start border-bottom py-3" style="gap:12px">
            <div>
                <div><strong>{{ $notice->data['title'] ?? 'Booking notification' }}</strong>
                    @unless($notice->read_at)<span class="badge bg-primary">Unread</span>@endunless
                </div>
                <div class="text-muted">{{ $notice->data['message'] ?? '' }}</div>
                <small class="text-muted">{{ $notice->created_at?->diffForHumans() }}</small>
            </div>
            <div class="d-flex flex-wrap" style="gap:8px">
                @if(in_array($notice->data['kind'] ?? '', ['booking_requested','reschedule_requested','booking_deadline','booking_status','reschedule_reviewed'], true))
                    <a class="btn btn-sm btn-outline-primary" href="{{ $notice->data['url'] ?? '#' }}">Open</a>
                @endif
                @unless($notice->read_at)
                    <form method="POST" action="{{ route('notifications.read', $notice->id) }}">@csrf @method('PATCH')
                        <button class="btn btn-sm btn-outline-secondary" type="submit">Mark read</button>
                    </form>
                @endunless
            </div>
        </div>
    @empty<p>No notifications yet.</p>@endforelse
    {{ $notices->links() }}
</div></div>
@endsection
