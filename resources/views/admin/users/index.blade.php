@extends('layouts.app')

@section('title', 'Users')

@include('partials.admin-ui')

@section('content')

    @php
        $adminCount = $counts['admin'];
    @endphp

    {{-- Page header --}}
    <x-page-header>
        <x-slot:title>Users</x-slot:title>
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}" class="btn-icon btn-icon-primary">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New User
            </a>
        </x-slot:actions>
    </x-page-header>

    @include('partials.list-search')

    {{-- Filter chips --}}
    @php
        $filters = [
            null    => ['label' => 'All',    'count' => $counts['all']],
            'admin' => ['label' => 'Admins', 'count' => $counts['admin']],
            'staff' => ['label' => 'Staff',  'count' => $counts['staff']],
        ];
    @endphp
    <div class="admin-chips" style="margin-bottom: 20px;">
        @foreach ($filters as $key => $f)
            @php
                $isActive = $role === ($key ?: null);
                $url = route('admin.users.index', array_filter(['q' => request('q'), 'role' => $key], fn ($value) => $value !== null && $value !== ''));
            @endphp
            <a href="{{ $url }}" class="admin-chip {{ $isActive ? 'is-active' : '' }}" @if($isActive) aria-current="page" @endif>
                {{ $f['label'] }}
                <span class="chip-count">{{ $f['count'] }}</span>
            </a>
        @endforeach
    </div>

    {{-- Table --}}
    <div class="admin-card">
        @if ($users->isEmpty() && request()->filled('q'))
<div class="admin-empty"><h6>No matching results</h6><a class="btn btn-outline-secondary" href="{{ url()->current() }}">Clear filters</a></div>
@elseif ($users->isEmpty())
            <div class="admin-empty">
                <div class="admin-empty-icon">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                </div>
                <h6>No users found</h6>
                <a href="{{ route('admin.users.create') }}" class="btn-icon btn-icon-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Create User
                </a>
            </div>
        @else
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Employee ID</th>
                            <th class="text-center">Role</th>
                            <th class="text-center">Bookings</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            @php
                                $isSelf = auth()->id() === $user->id;
                            @endphp
                            <tr>
                                <td>
                                    <div class="row-main">
                                        {{ $user->name }}
                                        @if ($isSelf)
                                            <span class="soft-badge soft-badge-primary" style="margin-left:6px;">You</span>
                                        @endif
                                    </div>
                                    <div class="row-sub">{{ $user->email }}</div>
                                </td>

                                <td>
                                    @if ($user->employee_id)
                                        <span class="time-chip" style="font-variant-numeric: normal;">
                                            {{ $user->employee_id }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    @if ($user->role === 'admin')
                                        <span class="soft-badge soft-badge-info">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:3px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                            Admin
                                        </span>
                                    @else
                                        <span class="soft-badge soft-badge-secondary">
                                            Staff
                                        </span>
                                    @endif
                                </td>

                                <td class="text-center">
                                    @if ($user->bookings_count > 0)
                                        <span class="time-chip">{{ $user->bookings_count }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td class="text-end">
                                    <div class="actions-cell">
                                        <a href="{{ route('admin.users.edit', $user) }}"
                                           class="btn-icon btn-icon-outline"
                                           title="Edit user">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                            Edit
                                        </a>

                                        @if (! $isSelf)
                                            <form action="{{ route('admin.users.destroy', $user) }}"
                                                  method="POST"
                                                  class="d-inline delete-form"
                                                  data-name="{{ $user->name }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn-icon btn-icon-outline-danger" title="Delete user">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-2 14a2 2 0 0 1-2 2H9a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('admin.partials.delete-confirm-script')
<div class="mt-3">{{ $users->links() }}</div>
@endsection
