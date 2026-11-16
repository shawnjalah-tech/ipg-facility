@extends('layouts.app')

@section('title', 'New User')

@include('partials.admin-ui')

@section('content')

    <a href="{{ route('admin.users.index') }}" class="admin-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to Users
    </a>

    <x-page-header>
        <x-slot:title>New User</x-slot:title>
    </x-page-header>

    <div class="row">
        <div class="col-lg-8 col-xl-7">
            <div class="admin-card">
                <form method="POST" action="{{ route('admin.users.store') }}" class="admin-form">
                    @csrf
                    @include('admin.users._form', ['user' => null])

                    <div class="admin-form-footer" style="margin-top: 24px;">
                        <button type="submit" class="btn-icon btn-icon-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                            Create User
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="btn-icon btn-icon-outline">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
