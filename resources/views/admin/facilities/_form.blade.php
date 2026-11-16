@php
    $isEdit = $facility !== null;
    $activeDefault = $isEdit ? $facility->is_active : true;
    $activeChecked = old('_facility_form_submitted') ? (bool) old('is_active', false) : $activeDefault;
@endphp

<div class="admin-form-grid">

    <div class="admin-field">
        <label for="name">Facility Name<span class="req">*</span></label>
        <input type="text"
               name="name"
               id="name"
               class="admin-input @error('name') is-invalid @enderror"
               value="{{ old('name', $facility->name ?? '') }}"
               maxlength="255"
               required>
        @error('name')
            <div class="admin-error">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="admin-form-grid two-col" style="gap: 20px;">
        <div class="admin-field">
            <label for="category_id">Category<span class="req">*</span></label>
            <select name="category_id"
                    id="category_id"
                    class="admin-select @error('category_id') is-invalid @enderror"
                    required>
                <option value="">— Select a category —</option>
                @foreach ($categories as $cat)
                    <option
                        value="{{ $cat->id }}"
                        {{ (string) old('category_id', $facility?->category_id ?? request('category_id')) === (string) $cat->id ? 'selected' : '' }}
                    >
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
            @error('category_id')
                <div class="admin-error">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    {{ $message }}
                </div>
            @enderror
            @if ($categories->isEmpty())
                <div class="admin-error" style="color:#c73d58;">
                    No categories exist. <a href="{{ route('admin.categories.create') }}" style="color:#3eb7ba;font-weight:500;">Create one first</a>.
                </div>
            @endif
        </div>

        <div class="admin-field">
            <label for="capacity">Capacity (people)</label>
            <input type="number"
                   name="capacity"
                   id="capacity"
                   class="admin-input @error('capacity') is-invalid @enderror"
                   value="{{ old('capacity', $facility->capacity ?? '') }}"
                   min="1"
                   max="10000">
            @error('capacity')
                <div class="admin-error">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>

    <div class="admin-field">
        <label for="description">Description</label>
        <textarea name="description"
                  id="description"
                  class="admin-textarea @error('description') is-invalid @enderror"
                  rows="3"
                  maxlength="1000">{{ old('description', $facility->description ?? '') }}</textarea>
        @error('description')
            <div class="admin-error">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="admin-field">
        <input type="hidden" name="_facility_form_submitted" value="1">
        @if($isEdit && $facility->is_active && isset($affectedBookings) && $affectedBookings->isNotEmpty())
            <div class="alert alert-warning" role="alert">
                <strong>{{ $affectedBookings->count() }} approved booking(s) still scheduled or ongoing.</strong>
                <p style="margin:8px 0">Turning off "Accept bookings" stops new requests, but does not cancel existing bookings. Please review the reservations below before proceeding.</p>
                <ul style="margin-bottom:8px">
                    @foreach($affectedBookings->take(5) as $existing)
                        <li><a href="{{ route('bookings.show', $existing) }}">#{{ $existing->id }}</a> · {{ $existing->user?->name ?? 'Staff' }} · {{ $existing->date_range }}</li>
                    @endforeach
                    @if($affectedBookings->count() > 5)
                        <li>...and {{ $affectedBookings->count() - 5 }} more</li>
                    @endif
                </ul>
                <label style="display:flex;gap:8px;align-items:flex-start">
                    <input type="checkbox" name="confirm_deactivation" value="1" @checked(old('confirm_deactivation'))>
                    <span>I understand that these approved bookings remain valid and will need separate review.</span>
                </label>
            </div>
        @endif
        @error('is_active')<div class="admin-error">{{ $message }}</div>@enderror

        <div style="background:#fafbfc;border:1px solid #eef1f4;border-radius:10px;padding:14px 16px;">
            <label class="admin-check">
                <input type="checkbox"
                       name="is_active"
                       id="is_active"
                       value="1"
                       {{ $activeChecked ? 'checked' : '' }}>
                <span>Accept bookings</span>
            </label>
        </div>
    </div>

</div>
