<header id="page-topbar">
    <button type="button" class="topbar-toggle" aria-label="Toggle sidebar" aria-controls="main-navigation" aria-expanded="true">
        <i class="mdi mdi-menu"></i>
    </button>

    <a href="{{ route('dashboard') }}" class="topbar-brand">
        @if (file_exists(public_path('images/logo.png')))
            <img src="{{ asset('images/logo.png') }}" alt="Logo" class="topbar-logo">
        @endif
        <span>{{ config('app.name') }}</span>
    </a>

    <div class="topbar-spacer"></div>

    <a href="{{ route('notifications.index') }}" class="btn btn-outline-secondary btn-sm me-2" aria-label="Open notifications">
        <i class="mdi mdi-bell-outline"></i> Notifications
        @php($unreadCount = auth()->user()->unreadNotifications()->count())
        @if($unreadCount > 0)<span class="badge bg-danger">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>@endif
    </a>
    <div class="topbar-user">
        <button type="button" class="topbar-user-toggle" aria-controls="account-menu" aria-expanded="false">
            <span class="topbar-user-avatar">
                {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
            </span>
            <span>
                <span class="topbar-user-name d-block">{{ auth()->user()->name ?? 'Guest' }}</span>
            </span>
            <i class="mdi mdi-chevron-down"></i>
        </button>

        <div class="topbar-dropdown" id="account-menu">
            <a href="{{ route('dashboard') }}">
                <i class="mdi mdi-view-dashboard"></i> Dashboard
            </a>
            @if (Route::has('staff.my-bookings'))
                <a href="{{ route('staff.my-bookings') }}">
                    <i class="mdi mdi-calendar-check"></i> My Bookings
                </a>
            @endif
            <hr>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit">
                    <i class="mdi mdi-logout"></i> Logout
                </button>
            </form>
        </div>
    </div>
</header>
