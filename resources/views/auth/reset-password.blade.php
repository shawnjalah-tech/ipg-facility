@extends('layouts.auth')
@section('title', 'Choose a password')
@section('content')
<div class="auth-card-header"><h1>Choose a new password</h1></div>
@foreach($errors->all() as $error)<p class="alert-auth" role="alert">{{ $error }}</p>@endforeach
<form method="POST" action="{{ route('password.update') }}">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<div class="field"><label for="email">Email address</label><div class="field-input"><input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></div></div>
<div class="field"><label for="password">New password</label><div class="field-input"><input id="password" type="password" name="password" minlength="8" required autocomplete="new-password"></div></div>
<div class="field"><label for="password_confirmation">Confirm password</label><div class="field-input"><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"></div></div>
<button class="btn-auth" type="submit">Reset password</button></form>
@endsection
