@php
    $isEdit  = $user !== null;
    $isSelf  = $isEdit && auth()->id() === $user->id;
    $isLastAdmin = $isEdit && $user->role === 'admin' && \App\Models\User::where('role', 'admin')->count() <= 1;
@endphp

<div class="admin-form-grid">

    {{-- Name --}}
    <div class="admin-field">
        <label for="name">Full Name<span class="req">*</span></label>
        <input type="text"
               name="name"
               id="name"
               class="admin-input @error('name') is-invalid @enderror"
               value="{{ old('name', $user->name ?? '') }}"
               maxlength="255"
               required>
        @error('name')
            <div class="admin-error">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ $message }}
            </div>
        @enderror
    </div>

    {{-- Email --}}
    <div class="admin-field">
        <label for="email">Email Address<span class="req">*</span></label>
        <input type="email"
               name="email"
               id="email"
               class="admin-input @error('email') is-invalid @enderror"
               value="{{ old('email', $user->email ?? '') }}"
               maxlength="255"
               required>
        @error('email')
            <div class="admin-error">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="admin-form-grid two-col" style="gap: 20px;">
        {{-- Employee ID --}}
        <div class="admin-field">
            <label for="employee_id">Employee ID</label>
            <input type="text"
                   name="employee_id"
                   id="employee_id"
                   class="admin-input @error('employee_id') is-invalid @enderror"
                   value="{{ old('employee_id', $user->employee_id ?? '') }}"
                   maxlength="50">
            @error('employee_id')
                <div class="admin-error">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    {{ $message }}
                </div>
            @enderror
        </div>

        {{-- Role --}}
        <div class="admin-field">
            <label for="role">Role<span class="req">*</span></label>
            <select name="role"
                    id="role"
                    class="admin-select @error('role') is-invalid @enderror"
                    @if ($isSelf || $isLastAdmin) disabled @endif
                    required>
                <option value="staff" {{ old('role', $user->role ?? 'staff') === 'staff' ? 'selected' : '' }}>
                    Staff
                </option>
                <option value="admin" {{ old('role', $user->role ?? 'staff') === 'admin' ? 'selected' : '' }}>
                    Admin{{ $isSelf ? ' (your account)' : ($isLastAdmin ? ' (last admin)' : '') }}
                </option>
            </select>

            @if ($isSelf || $isLastAdmin)
                {{-- Disabled select doesn't submit; add hidden input with the current value --}}
                <input type="hidden" name="role" value="{{ $user->role }}">
            @endif

            @error('role')
                <div class="admin-error">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    {{ $message }}
                </div>
            @enderror
        </div>
    </div>

    @if($isEdit)
        <details class="password-controls" @if($errors->has('password')) open @endif>
            <summary>Change password</summary>
    @endif
    {{-- Password --}}
    <div class="admin-form-grid two-col" style="gap: 20px;">
        <div class="admin-field">
            <label for="password">Password @if (! $isEdit)<span class="req">*</span>@endif</label>
            <input type="password"
                   name="password"
                   id="password"
                   class="admin-input @error('password') is-invalid @enderror"
                   minlength="8" autocomplete="new-password"
                   {{ $isEdit ? '' : 'required' }}>
            @error('password')
                <div class="admin-error">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-top:3px;flex-shrink:0;"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    {{ $message }}
                </div>
            @enderror
        </div>

        <div class="admin-field">
            <label for="password_confirmation">
                Confirm Password @if (! $isEdit)<span class="req">*</span>@endif
            </label>
            <input type="password"
                   name="password_confirmation"
                   id="password_confirmation"
                   class="admin-input"
                   autocomplete="new-password"
                   {{ $isEdit ? '' : 'required' }}>
        </div>
    </div>

    @if($isEdit)</details>@endif

</div>
