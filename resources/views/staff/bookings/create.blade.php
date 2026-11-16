@extends('layouts.app')

@section('title', 'Book a Facility')

@include('partials.admin-ui')


@section('content')

    {{-- Page header --}}
    <x-page-header>
        <x-slot:title>Book a Facility</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('calendar.index') }}" id="check-availability-link" class="btn-icon btn-icon-outline">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Check Availability
            </a>
        </x-slot:actions>
    </x-page-header>

    @if ($categories->filter(fn ($c) => $c->facilities->isNotEmpty())->isEmpty())
        {{-- No facilities available --}}
        <div class="admin-card">
            <div class="admin-empty">
                <div class="admin-empty-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                </div>
                <h6>No facilities available</h6>
                <a href="{{ route('calendar.index') }}" class="btn-icon btn-icon-outline">
                    View Facility Availability
                </a>
            </div>
        </div>
    @else
        <div class="row">

            {{-- Booking form --}}
            <div class="col-lg-8">
                <div class="admin-card">
                    <form method="POST" action="{{ route('staff.bookings.store') }}" class="admin-form" id="booking-form">
                        @csrf


                        <div class="admin-form-grid">

                            {{-- Facility --}}
                            <div class="admin-field">
                                <label for="facility_id">Facility<span class="req">*</span></label>
                                <select name="facility_id"
                                        id="facility_id"
                                        class="admin-select @error('facility_id') is-invalid @enderror"
                                        required>
                                    <option value="">— Select a facility —</option>
                                    @foreach ($categories as $category)
                                        @if ($category->facilities->isNotEmpty())
                                            <optgroup label="{{ $category->name }}">
                                                @foreach ($category->facilities as $facility)
                                                    <option value="{{ $facility->id }}"
                                                        {{ (string) old('facility_id', request()->query('facility_id')) === (string) $facility->id ? 'selected' : '' }}>
                                                        {{ $facility->name }}
                                                        @if ($facility->capacity)
                                                            ({{ $facility->capacity }} pax)
                                                        @endif
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                                @error('facility_id')
                                    <div class="admin-error">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        {{ $message }}
                                    </div>
                                @enderror

                            </div>

                            {{-- Date range --}}
                            <div class="admin-form-grid two-col" style="gap: 20px;">
                                <div class="admin-field">
                                    <label for="start_date">Start Date<span class="req">*</span></label>
                                    <input type="date"
                                           name="start_date"
                                           id="start_date"
                                           class="admin-input @error('start_date') is-invalid @enderror"
                                           value="{{ old('start_date', request()->query('start_date', now()->toDateString())) }}"
                                           min="{{ now()->toDateString() }}"
                                           required>
                                    @error('start_date')
                                        <div class="admin-error">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <div class="admin-field">
                                    <label for="end_date">End Date<span class="req">*</span></label>
                                    <input type="date"
                                           name="end_date"
                                           id="end_date"
                                           class="admin-input @error('end_date') is-invalid @enderror"
                                           value="{{ old('end_date', request()->query('end_date', now()->toDateString())) }}"
                                           min="{{ now()->toDateString() }}"
                                           required>
                                    @error('end_date')
                                        <div class="admin-error">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            </div>

                            <x-booking-time-fields
                                :start-time="old('start_time', request()->query('start_time'))"
                                :end-time="old('end_time', request()->query('end_time'))" />

                            {{-- Purpose --}}
                            <x-booking-repeat-fields />
                            <div class="admin-field">
                                <label for="purpose">Purpose</label>
                                <textarea name="purpose"
                                          id="purpose"
                                          class="admin-textarea @error('purpose') is-invalid @enderror"
                                          rows="3"
                                          maxlength="500">{{ old('purpose') }}</textarea>
                                @error('purpose')
                                    <div class="admin-error">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                        {{ $message }}
                                    </div>
                                @enderror

                            </div>

                        </div>

                        {{-- Submit --}}
                        <div class="admin-form-footer" style="margin-top: 28px; padding-top: 20px; border-top: 1px solid #eef1f4;">
                            <button type="submit" class="btn-icon btn-icon-primary" id="submit-btn">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                <span id="submit-label">Submit Request</span>
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn-icon btn-icon-outline">
                                Cancel
                            </a>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    @endif

@endsection

@push('scripts')
<script src="{{ asset('js/booking-schedule.js') }}"></script>
<script>
(function () {
    var select = document.getElementById('facility_id');
    var start = document.getElementById('start_date');
    var link = document.getElementById('check-availability-link');
    function updateAvailabilityLink() {
        if (!link) return;
        var url = new URL(@json(route('calendar.index')), window.location.origin);
        if (select && select.value) url.searchParams.set('facility', select.value);
        if (start && start.value) url.searchParams.set('week', start.value);
        link.href = url.toString();
    }
    if (select) select.addEventListener('change', updateAvailabilityLink);
    if (start) start.addEventListener('change', updateAvailabilityLink);
    updateAvailabilityLink();
})();
</script>
@endpush
