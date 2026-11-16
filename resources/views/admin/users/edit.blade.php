@extends('layouts.app')

@section('title', 'Edit User')

@include('partials.admin-ui')

@section('content')

    <a href="{{ route('admin.users.index') }}" class="admin-back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Back to Users
    </a>

    <x-page-header>
        <x-slot:title>Edit User</x-slot:title>
    </x-page-header>

    <div class="row">
        <div class="col-lg-8 col-xl-7">
            <div class="admin-card">
                <form method="POST" action="{{ route('admin.users.update', $user) }}" class="admin-form">
                    @csrf
                    @method('PUT')
                    @include('admin.users._form', ['user' => $user])

                    <div class="admin-form-footer" style="margin-top: 24px;">
                        <button type="submit" class="btn-icon btn-icon-primary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Changes
                        </button>
                        <a href="{{ route('admin.users.index') }}" class="btn-icon btn-icon-outline">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            {{-- Danger zone --}}
            @if (! $isSelf)
                <div class="admin-card" style="margin-top: 16px;">
                    <div class="admin-card-pad">
                        <h6 style="font-size:0.875rem;font-weight:600;color:#343a40;margin:0 0 6px;display:flex;align-items:center;gap:8px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#c73d58" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            Danger zone
                        </h6>
                        <p style="font-size:0.8125rem;color:#6c757d;margin:0 0 14px;line-height:1.55;">
                            Deleting a user permanently removes their account and any bookings they own.
                            Cannot be undone.
                        </p>
                        <form action="{{ route('admin.users.destroy', $user) }}"
                              method="POST"
                              class="delete-form"
                              data-name="{{ $user->name }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn-icon btn-icon-outline-danger">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                Delete User
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('admin.partials.delete-confirm-script')
@endsection
