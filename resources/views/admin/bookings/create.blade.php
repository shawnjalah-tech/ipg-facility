@extends('layouts.app')

@section('title', 'Book for Staff')
@include('partials.admin-ui')

@section('content')
    <x-page-header>
        <x-slot:title>Book a Facility for Staff</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('calendar.index') }}" class="btn-icon btn-icon-outline">Check availability</a>
            <a href="{{ route('admin.bookings.index') }}" class="btn-icon btn-icon-outline">Booking History</a>
        </x-slot:actions>
    </x-page-header>

    @if($staffMembers->isEmpty() || $categories->every(fn ($category) => $category->facilities->isEmpty()))
        <div class="admin-card p-4">
            @if($staffMembers->isEmpty())
                <p>No staff accounts are available. <a href="{{ route('admin.users.index') }}">Manage users</a> to add one.</p>
            @else
                <p>No active facilities are available. <a href="{{ route('admin.facilities.index') }}">Manage facilities</a> first.</p>
            @endif
        </div>
    @else
        <noscript><form method="GET" action="{{ route('admin.bookings.create') }}" class="mb-3"><label for="staff_q">Search staff accounts by name or ID</label><div class="d-flex" style="gap:8px"><input class="form-control" id="staff_q" name="staff_q" value="{{ request('staff_q') }}"><button class="btn-icon btn-icon-outline">Find staff</button></div></form></noscript>
        <div class="row">
            <div class="col-lg-9 col-xl-8">
                <div class="admin-card">
                    <form method="POST" action="{{ route('admin.bookings.store') }}" class="admin-form">
                        @csrf
                        <div class="admin-form-grid">
                            <div class="admin-field">
                                <label for="user_id">Staff member <span class="req">*</span></label>
                                <select id="user_id" name="user_id" class="admin-select @error('user_id') is-invalid @enderror" required>
                                    <option value="">— Select a staff member —</option>
                                    @foreach($staffMembers as $staff)
                                        <option value="{{ $staff->id }}" @selected((string) old('user_id') === (string) $staff->id)>
                                            {{ $staff->name }}@if($staff->employee_id) ({{ $staff->employee_id }})@endif — {{ $staff->email }}
                                        </option>
                                    @endforeach
                                </select>
                                <div id="admin-staff-picker" class="staff-picker" data-search-url="{{ route('admin.staff-search') }}" hidden>
                                    <label for="staff-search" class="visually-hidden">Search for a staff member</label>
                                    <input type="search" id="staff-search" class="admin-input staff-picker-search @error('user_id') is-invalid @enderror"
                                           placeholder="Type name, employee ID, or email…" autocomplete="off"
                                           role="combobox" aria-autocomplete="list" aria-haspopup="listbox"
                                           aria-expanded="false" aria-controls="staff-search-results"
                                           aria-describedby="staff-search-feedback">
                                    <div id="staff-search-results" class="staff-picker-results" role="listbox" aria-label="Matching staff members" hidden></div>
                                    <div id="staff-search-feedback" class="staff-picker-status" aria-live="polite"></div>
                                </div>
                            </div>
                            <div class="admin-field">
                                <label for="facility_id">Facility <span class="req">*</span></label>
                                <select id="facility_id" name="facility_id" class="admin-select @error('facility_id') is-invalid @enderror" required>
                                    <option value="">— Select a facility —</option>
                                    @foreach($categories as $category)
                                        @if($category->facilities->isNotEmpty())
                                            <optgroup label="{{ $category->name }}">
                                                @foreach($category->facilities as $facility)
                                                    <option value="{{ $facility->id }}" @selected((string) old('facility_id', request('facility_id')) === (string) $facility->id)>
                                                        {{ $facility->name }}@if($facility->capacity) ({{ $facility->capacity }} pax)@endif
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                            <div class="admin-form-grid two-col" style="gap:20px;">
                                <div class="admin-field">
                                    <label for="start_date">Start Date <span class="req">*</span></label>
                                    <input class="admin-input @error('start_date') is-invalid @enderror" type="date" name="start_date" id="start_date"
                                           value="{{ old('start_date', request('start_date', now()->toDateString())) }}" min="{{ now()->toDateString() }}" required>
                                </div>
                                <div class="admin-field">
                                    <label for="end_date">End Date <span class="req">*</span></label>
                                    <input class="admin-input @error('end_date') is-invalid @enderror" type="date" name="end_date" id="end_date"
                                           value="{{ old('end_date', request('end_date', now()->toDateString())) }}" min="{{ now()->toDateString() }}" required>
                                </div>
                            </div>
                            <x-booking-time-fields
                                :start-time="old('start_time', request('start_time'))"
                                :end-time="old('end_time', request('end_time'))" />
                            <x-booking-repeat-fields />
                            <div class="admin-field">
                                <label for="purpose">Purpose</label>
                                <textarea name="purpose" id="purpose" class="admin-textarea @error('purpose') is-invalid @enderror" maxlength="500" rows="3" placeholder="Meeting, training, workshop…">{{ old('purpose') }}</textarea>
                            </div>
                        </div>
                        <div class="admin-form-footer" style="margin-top:28px; padding-top:20px; border-top:1px solid #eef1f4;">
                            <button type="submit" class="btn-icon btn-icon-primary">Create &amp; approve booking</button>
                            <a href="{{ route('admin.bookings.index') }}" class="btn-icon btn-icon-outline">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
<script src="{{ asset('js/booking-schedule.js') }}"></script>
@endpush

@push('styles')
<link rel="stylesheet" href="{{ asset('css/admin-staff-picker.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/admin-staff-picker.js') }}"></script>
@endpush
