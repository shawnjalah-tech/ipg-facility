@php $isEdit = $category !== null; @endphp

<div class="admin-form-grid">

    <div class="admin-field">
        <label for="name">Name<span class="req">*</span></label>
        <input type="text"
               name="name"
               id="name"
               class="admin-input @error('name') is-invalid @enderror"
               value="{{ old('name', $category->name ?? '') }}"
               maxlength="255"
               required>
        @error('name')
            <div class="admin-error">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="admin-field">
        <label for="description">Description</label>
        <textarea name="description"
                  id="description"
                  class="admin-textarea @error('description') is-invalid @enderror"
                  rows="4"
                  maxlength="1000">{{ old('description', $category->description ?? '') }}</textarea>
        @error('description')
            <div class="admin-error">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ $message }}
            </div>
        @enderror

    </div>

</div>
