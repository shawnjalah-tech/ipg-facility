@extends('layouts.app')
@section('title', 'Facility Closures')
@include('partials.admin-ui')
@section('content')
<x-page-header>
    <x-slot:title>Facility Closures &amp; Maintenance</x-slot:title>
    <x-slot:actions><a class="btn-icon btn-icon-outline" href="{{ route('admin.facilities.index') }}">Back to Facilities</a></x-slot:actions>
</x-page-header>
<div class="admin-card p-4 mb-4">
    <h2 class="h5 mb-3">Schedule closure</h2>
    <form method="POST" action="{{ route('admin.facility-closures.store') }}" class="admin-form">
        @csrf
        <div class="row">
            <div class="col-md-6 admin-field"><label for="facility_id">Facility *</label><select id="facility_id" name="facility_id" class="admin-select" required><option value="">Choose a facility</option>@foreach($facilities as $facility)<option value="{{ $facility->id }}" @selected((string) old('facility_id') === (string) $facility->id)>{{ $facility->name }}</option>@endforeach</select></div>
            <div class="col-md-3 admin-field"><label for="starts_at">From *</label><input id="starts_at" name="starts_at" class="admin-input" type="datetime-local" value="{{ old('starts_at') }}" required></div>
            <div class="col-md-3 admin-field"><label for="ends_at">Until *</label><input id="ends_at" name="ends_at" class="admin-input" type="datetime-local" value="{{ old('ends_at') }}" required></div>
            <div class="col-md-6 admin-field"><label for="reason">Reason *</label><input id="reason" class="admin-input" name="reason" maxlength="255" value="{{ old('reason') }}" placeholder="Maintenance / Examination / Event" required></div>
            <div class="col-md-6 admin-field"><label for="notes">Notes (optional)</label><input id="notes" class="admin-input" name="notes" maxlength="2000" value="{{ old('notes') }}"></div>
        </div>
        <label class="d-block mb-3"><input type="checkbox" name="confirm_affected" value="1" @checked(old('confirm_affected'))> Keep existing bookings that overlap this closure.</label>
        <button class="btn-icon btn-icon-primary" type="submit">Block this period</button>
    </form>
</div>
<div class="admin-card p-4">
    <form method="GET" class="mb-3 d-flex align-items-end" style="gap:10px;flex-wrap:wrap"><div><label for="filter_facility">Filter facility</label><select id="filter_facility" name="facility_id" class="form-control"><option value="">All facilities</option>@foreach($facilities as $f)<option value="{{ $f->id }}" @selected(request('facility_id') == $f->id)>{{ $f->name }}</option>@endforeach</select></div><button class="btn-icon btn-icon-outline" type="submit">Filter</button></form>
    <h2 class="h5">Scheduled closures</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>Facility</th><th>Period</th><th>Reason</th><th>Created by</th><th>Action</th></tr></thead><tbody>
        @forelse($closures as $closure)<tr><td>{{ $closure->facility?->name }}</td><td>{{ $closure->starts_at->format('d M Y H:i') }} – {{ $closure->ends_at->format('d M Y H:i') }}</td><td>{{ $closure->reason }}</td><td>{{ $closure->creator?->name ?? 'System' }}</td><td><a class="btn-icon btn-icon-outline mb-1" href="{{ route('admin.bookings.index', ['q' => $closure->facility?->name]) }}">Review bookings</a><form method="POST" action="{{ route('admin.facility-closures.destroy', $closure) }}" onsubmit="return confirm('Remove this closure? New bookings could become available.');">@csrf @method('DELETE')<button type="submit" class="btn-icon btn-icon-outline">Remove</button></form></td></tr>
        @empty<tr><td colspan="5" class="text-muted">No facility closures recorded.</td></tr>@endforelse
    </tbody></table></div>{{ $closures->links() }}
</div>
@endsection
