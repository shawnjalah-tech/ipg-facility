<div class="fb-day-panel">
    <div class="fb-panel-intro">
        <span class="fb-eyebrow">{{ $day->format('l, d F Y') }}</span>
        <h3>{{ $facility->name }}</h3>
        <p>{{ $facility->category?->name ?? 'Facility' }} · {{ config('app.timezone') }}</p>
    </div>
    @if($details['closures']->isNotEmpty())
        <section class="fb-panel-section" aria-label="Facility closures">
            <h4>Facility closures</h4>
            @foreach($details['closures'] as $closure)
                <article class="fb-detail-item fb-closure-item">
                    <span class="fb-state fb-state-closed">Closed</span>
                    <strong>{{ $closure->reason }}</strong>
                    <span>{{ $closure->starts_at->format('d M, H:i') }} – {{ $closure->ends_at->format('d M, H:i') }}</span>
                </article>
            @endforeach
        </section>
    @endif
    <section class="fb-panel-section" aria-label="Reservations">
        <h4>Bookings ({{ $details['bookings']->count() }})</h4>
        @forelse($details['bookings'] as $booking)
            <article class="fb-detail-item" id="fb-booking-{{ $booking->id }}">
                <div class="fb-detail-heading">
                    <strong>{{ $booking->user?->name ?? 'Unknown staff' }}</strong>
                    <span class="fb-state fb-state-{{ $booking->status }}">{{ ucfirst($booking->status) }}</span>
                </div>
                <div class="fb-detail-time">{{ $booking->start_time && $booking->end_time ? $booking->starts_at->format('d M, H:i').' – '.$booking->ends_at->format('d M, H:i') : $booking->date_range.' · All day' }}</div>
                <div class="fb-detail-purpose"><span>Purpose</span><p>{{ $booking->purpose ?: 'No purpose provided' }}</p></div>
                @if(auth()->user()->isAdmin() || $booking->user_id === auth()->id())
                    <a href="{{ route('bookings.show', $booking) }}" class="fb-text-action">View booking #{{ $booking->id }}</a>
                @endif
            </article>
        @empty
            <p class="fb-empty-day">No pending or approved bookings on this date.</p>
        @endforelse
    </section>
    <div class="fb-panel-actions">
        @if($canBook)
            <a class="btn btn-primary btn-sm" href="{{ $bookUrl }}">+ Book this date</a>
        @endif
        <a class="btn btn-outline-secondary btn-sm" href="{{ $slotsUrl }}">View time slots</a>
    </div>
</div>
