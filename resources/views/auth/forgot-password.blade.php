@extends('layouts.auth')
@section('title', 'Reset password')
@section('content')
<div class="auth-card-header"><h1>Forgot your password?</h1></div>
@if(session('status'))<p role="status">{{ session('status') }}</p>@endif
@foreach($errors->all() as $error)<p class="alert-auth" role="alert">{{ $error }}</p>@endforeach
<form method="POST" action="{{ route('password.email') }}">@csrf
<div class="field"><label for="email">Email address</label><div class="field-input"><input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required></div></div>
<button class="btn-auth" type="submit">Send reset link</button>
</form><p><a href="{{ route('login') }}">Back to sign in</a></p>
@endsection
