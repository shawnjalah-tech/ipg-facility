@php
    /** @var \App\Models\Booking $booking */
    $canApprove = $booking->status === 'pending';
    $canCancel  = in_array($booking->status, ['pending', 'approved'], true)
               && $booking->starts_at->isFuture();

    $statusClass = match ($booking->status) {
        'approved'  => 'soft-badge-success',
        'pending'   => 'soft-badge-warning',
        'rejected'  => 'soft-badge-danger',
        'cancelled' => 'soft-badge-secondary',
        'expired'   => 'soft-badge-secondary',
        default     => 'soft-badge-secondary',
    };
@endphp

<tr>
    {{-- Facility --}}
    <td>
        <a class="row-main" href="{{ route('bookings.show', $booking) }}">{{ $booking->facility->name ?? '—' }} · #{{ $booking->id }}</a>
        @if ($booking->facility?->category)
            <div class="row-sub">{{ $booking->facility->category->name }}</div>
        @endif
    </td>

    {{-- Requested by --}}
    <td>
        <div class="row-main">{{ $booking->user->name ?? '—' }}</div>
        @if ($booking->user?->employee_id)
            <div class="row-sub">{{ $booking->user->employee_id }}</div>
        @endif
    </td>

    {{-- Dates --}}
    <td>
        <div class="row-main" style="font-weight:500; white-space:nowrap;">
            {{ $booking->date_range }}
        </div>
    </td>

    {{-- Time --}}
    <td>
        @if ($booking->start_time && $booking->end_time)
            <span class="time-chip">
                {{ \Carbon\Carbon::parse($booking->start_time)->format('H:i') }}
                –
                {{ \Carbon\Carbon::parse($booking->end_time)->format('H:i') }}
            </span>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>

    {{-- Purpose --}}
    <td class="purpose-cell">
        @if ($booking->purpose)
            <div class="purpose-text">{{ $booking->purpose }}</div>
        @else
            <span class="text-muted">—</span>
        @endif
    </td>

    {{-- Status --}}
    <td class="text-center">
        <span class="soft-badge {{ $statusClass }}">
            {{ ucfirst($booking->status) }}
        </span>
    </td>

    {{-- Actions --}}
    <td class="text-end">
        <div class="actions-cell">
            @if (request()->routeIs('admin.bookings.index'))
                <a href="{{ route('admin.bookings.edit-status', $booking) }}" class="btn-icon btn-icon-outline" title="Edit booking status">
                    Edit status
                </a>
            @endif

            @if ($canApprove)
                <form action="{{ route('admin.bookings.approve', $booking) }}"
                      method="POST"
                      class="d-inline">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="btn-icon btn-icon-success" title="Approve">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Approve
                    </button>
                </form>

                <form action="{{ route('admin.bookings.reject', $booking) }}"
                      method="POST"
                      class="d-inline reject-form"
                      data-name="{{ $booking->user->name ?? 'user' }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="admin_remarks" value="">
                    <button type="submit" class="btn-icon btn-icon-danger" title="Reject">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        Reject
                    </button>
                </form>
            @endif

            @if ($canCancel)
                <form action="{{ route('admin.bookings.cancel', $booking) }}"
                      method="POST"
                      class="d-inline cancel-form"
                      data-name="{{ $booking->facility->name ?? 'booking' }}">
                    @csrf
                    @method('PUT')
                    <button type="submit" class="btn-icon btn-icon-outline-warning" title="Cancel booking">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
                        Cancel
                    </button>
                </form>
            @endif

            @if ($booking->admin_remarks)
                <button type="button"
                        class="btn-icon-square remarks-toggle"
                        title="View admin remarks"
                        data-remarks="{{ e($booking->admin_remarks) }}">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                </button>
            @endif

        </div>
    </td>
</tr>