@extends('layouts.app')
@section('title', 'Recurring Bookings')
@include('partials.admin-ui')
@section('content')
<x-page-header>
    <x-slot:title>Recurring Bookings</x-slot:title>
    <x-slot:actions><a class="btn-icon btn-icon-outline" href="{{ auth()->user()->isAdmin() ? route('admin.bookings.create') : route('staff.bookings.create') }}">Create booking</a></x-slot:actions>
</x-page-header>
<div class="admin-card">
    @if ($series->isEmpty())
        <div class="admin-empty"><h6>No recurring bookings yet</h6></div>
    @else
        <div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Series</th><th>Staff</th><th>Pattern</th><th>Pending</th><th>Action</th></tr></thead><tbody>
            @foreach ($series as $item)
            <tr>
                <td><div class="row-main">#{{ $item->id }} · {{ $item->facility?->name ?? 'Facility' }}</div><div class="row-sub">{{ $item->created_at->format('d M Y') }}</div></td>
                <td>{{ $item->owner?->name ?? 'Staff' }}</td>
                <td>{{ ucfirst($item->frequency) }} · {{ $item->occurrence_count }} bookings</td>
                <td>{{ $item->pending_count }}</td>
                <td><a class="btn-icon btn-icon-outline" href="{{ route('recurring.show', $item) }}">View series</a></td>
            </tr>
            @endforeach
        </tbody></table></div>
        <div class="p-3">{{ $series->links() }}</div>
    @endif
</div>
@endsection
