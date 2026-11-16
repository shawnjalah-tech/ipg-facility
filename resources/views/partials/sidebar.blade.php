@php
    $user    = auth()->user();
    $isAdmin = $user && $user->role === 'admin';
    $route   = request()->route()?->getName() ?? '';
    $is      = fn (string $pattern) => str_starts_with($route, $pattern) ? 'active' : '';
@endphp

<nav class="vertical-menu" aria-label="Main navigation">
    <ul class="sidebar-nav">

        {{-- ---------- Always visible ---------- --}}
        <li>
            <a href="{{ route('dashboard') }}" class="{{ $is('dashboard') ? 'active' : '' }}">
                <i class="mdi mdi-view-dashboard"></i>
                <span class="nav-label">Dashboard</span>
            </a>
        </li>

        @if (Route::has('calendar.index'))
            <li>
                <a href="{{ route('calendar.index') }}" class="{{ $is('calendar.') ? 'active' : '' }}">
                    <i class="mdi mdi-calendar-month"></i>
                    <span class="nav-label">Facility Availability</span>
                </a>
            </li>
        @endif

        {{-- ---------- Staff-only ---------- --}}
        @unless ($isAdmin)
            <li class="sidebar-section">Bookings</li>

            @if (Route::has('staff.bookings.create'))
                <li>
                    <a href="{{ route('staff.bookings.create') }}"
                       class="{{ $is('staff.bookings.create') ? 'active' : '' }}">
                        <i class="mdi mdi-plus-circle-outline"></i>
                        <span class="nav-label">Book a Facility</span>
                    </a>
                </li>
            @endif

            @if (Route::has('staff.my-bookings'))
                <li>
                    <a href="{{ route('staff.my-bookings') }}"
                       class="{{ $is('staff.my-bookings') ? 'active' : '' }}">
                        <i class="mdi mdi-format-list-bulleted"></i>
                        <span class="nav-label">My Bookings</span>
                    </a>
                </li>
            @endif
            <li>
                <a href="{{ route('recurring.index') }}" class="{{ $is('recurring.') ? 'active' : '' }}">
                    <i class="mdi mdi-calendar-refresh"></i>
                    <span class="nav-label">Recurring Bookings</span>
                </a>
            </li>
        @endunless

        {{-- ---------- Admin-only ---------- --}}
        @if ($isAdmin)
            @if (app()->environment('loadtest'))
                <li>
                    <a href="{{ route('admin.load-test.index') }}" class="{{ $is('admin.load-test.') ? 'active' : '' }}">
                        <i class="mdi mdi-speedometer"></i><span class="nav-label">Load Test Lab</span>
                    </a>
                </li>
            @endif
            <li>
                <a href="{{ route('admin.system.health') }}" class="{{ $route === 'admin.system.health' ? 'active' : '' }}">
                    <i class="mdi mdi-heart-pulse"></i>
                    <span class="nav-label">System Health</span>
                </a>
            </li>
            <li class="sidebar-section">Management</li>
            <li>
                <a href="{{ route('admin.bookings.create') }}" class="{{ $route === 'admin.bookings.create' ? 'active' : '' }}">
                    <i class="mdi mdi-calendar-plus"></i>
                    <span class="nav-label">Book for Staff</span>
                </a>
            </li>

            <li><a href="{{ route('admin.facility-closures.index') }}" class="{{ $is('admin.facility-closures.') ? 'active' : '' }}"><i class="mdi mdi-calendar-remove"></i><span class="nav-label">Facility Closures</span></a></li>
            @if (Route::has('admin.facilities.index'))
                <li>
                    <a href="{{ route('admin.facilities.index') }}"
                       class="{{ $is('admin.categories.') || $is('admin.facilities.') ? 'active' : '' }}">
                        <i class="mdi mdi-office-building"></i>
                        <span class="nav-label">Facilities &amp; Categories</span>
                    </a>
                </li>
            @endif
                                    @if (Route::has('admin.users.index'))
                <li>
                    <a href="{{ route('admin.users.index') }}"
                       class="{{ $is('admin.users.') ? 'active' : '' }}">
                        <i class="mdi mdi-account-multiple"></i>
                        <span class="nav-label">Users</span>
                    </a>
                </li>
            @endif

            <li class="sidebar-section">Bookings</li>
            <li>
                <a href="{{ route('recurring.index') }}" class="{{ $is('recurring.') ? 'active' : '' }}">
                    <i class="mdi mdi-calendar-refresh"></i>
                    <span class="nav-label">Recurring Bookings</span>
                </a>
            </li>
            <li>
                <a href="{{ route('admin.bookings.manage') }}" class="{{ $route === 'admin.bookings.manage' ? 'active' : '' }}">
                    <i class="mdi mdi-clipboard-check-outline"></i>
                    <span class="nav-label">Booking Management</span>
                </a>
            </li>

            @if (Route::has('admin.bookings.index'))
                <li>
                    <a href="{{ route('admin.bookings.index') }}"
                       class="{{ $is('admin.bookings.index') ? 'active' : '' }}">
                        <i class="mdi mdi-book-open-variant"></i>
                        <span class="nav-label">Booking History</span>
                    </a>
                </li>
            @endif

            <li>
                <a href="{{ route('admin.analytics.index') }}" class="{{ $is('admin.analytics.') ? 'active' : '' }}">
                    <i class="mdi mdi-chart-bar"></i>
                    <span class="nav-label">Facility Analytics</span>
                </a>
            </li>
            @if (Route::has('admin.reports.monthly'))
                <li>
                    <a href="{{ route('admin.reports.monthly') }}"
                       class="{{ $is('admin.reports.') ? 'active' : '' }}">
                        <i class="mdi mdi-file-chart"></i>
                        <span class="nav-label">Reports</span>
                    </a>
                </li>
            @endif
        @endif

    </ul>
</nav>