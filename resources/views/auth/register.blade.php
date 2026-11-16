@extends('layouts.auth')

@section('title', 'Register')

@section('content')

    <div class="auth-card-header">
        <h1>Create your account</h1>

    </div>

    @if ($errors->any())
        <div class="alert-auth">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <line x1="12" y1="8" x2="12" y2="12"/>
                <line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" novalidate>
        @csrf

        {{-- Name --}}
        <div class="field">
            <label for="name">Full Name</label>
            <div class="field-input">
                <input type="text"
                       name="name"
                       id="name"
                       value="{{ old('name') }}"
                       autocomplete="name"
                       autofocus
                       required
                       class="@error('name') is-invalid @enderror">
                <span class="field-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </span>
            </div>
        </div>

        {{-- Email --}}
        <div class="field">
            <label for="email">Email Address</label>
            <div class="field-input">
                <input type="email"
                       name="email"
                       id="email"
                       value="{{ old('email') }}"
                       autocomplete="email"
                       required
                       class="@error('email') is-invalid @enderror">
                <span class="field-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </span>
            </div>
        </div>

        {{-- Employee ID --}}
        <div class="field">
            <label for="employee_id">
                Employee ID
            </label>
            <div class="field-input">
                <input type="text"
                       name="employee_id"
                       id="employee_id"
                       value="{{ old('employee_id') }}"
                       class="@error('employee_id') is-invalid @enderror">
                <span class="field-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                        <circle cx="9" cy="10" r="2"/>
                        <path d="M15 8h4"/>
                        <path d="M15 12h4"/>
                        <path d="M6 18c0-1.66 1.34-3 3-3s3 1.34 3 3"/>
                    </svg>
                </span>
            </div>
        </div>

        {{-- Password --}}
        <div class="field">
            <label for="password">Password</label>
            <div class="field-input">
                <input type="password" minlength="8"
                       name="password"
                       id="password"
                       autocomplete="new-password"
                       required
                       class="@error('password') is-invalid @enderror">
                <span class="field-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </span>
                <button type="button"
                        class="field-toggle"
                        aria-label="Show password" aria-pressed="false"
                        tabindex="-1">
                    <svg class="icon-eye" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    <svg class="icon-eye-off" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                        <line x1="1" y1="1" x2="23" y2="23"/>
                    </svg>
                </button>
            </div>
        </div>

        {{-- Confirm Password --}}
        <div class="field">
            <label for="password_confirmation">Confirm Password</label>
            <div class="field-input">
                <input type="password" minlength="8"
                       name="password_confirmation"
                       id="password_confirmation"
                       autocomplete="new-password"
                       required>
                <span class="field-icon">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 9.9-1"/>
                    </svg>
                </span>
            </div>
        </div>

        <button type="submit" class="btn-auth" style="margin-top: 10px;">
            <span class="spinner"></span>
            <span class="btn-label">Create Account</span>
            <svg class="btn-label" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="5" y1="12" x2="19" y2="12"/>
                <polyline points="12 5 19 12 12 19"/>
            </svg>
        </button>
    </form>

    <p class="auth-alt">
        <a href="{{ route('login') }}">Back to sign in</a>
    </p>

@endsection
