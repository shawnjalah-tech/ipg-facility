<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name') }}</title>

    <link href="{{ asset('assets/fonts/poppins.css') }}" rel="stylesheet">

    {{-- Theme CSS (order matters) --}}
    <link href="{{ asset('assets/css/bootstrap.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app-style.css') }}" rel="stylesheet">
    {{-- Structural fallback: must load last --}}
    <link href="{{ asset('css/layout-shell.css') }}" rel="stylesheet">

    <link href="{{ asset('css/interface.css') }}" rel="stylesheet">
    @stack('styles')
</head>
<body>

<a class="skip-link" href="#main-content">Skip to content</a>
<div id="layout-wrapper">

    {{-- Topbar --}}
    @include('partials.topbar')

    {{-- Sidebar --}}
    @include('partials.sidebar')

    {{-- Main content --}}
    <main class="main-content" id="main-content" tabindex="-1">
        <div class="page-content">
            <div class="container-fluid">

                {{-- Flash messages --}}
                @if (session('status'))
                    <div class="alert alert-success" role="status">{{ session('status') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                {{-- Page content --}}
                @yield('content')

            </div>
        </div>

        {{-- Footer --}}
        @include('partials.footer')
    </main>
</div>

{{-- Scripts (order matters) --}}
<script src="{{ asset('assets/libs/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('assets/libs/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('assets/libs/metismenu/metisMenu.min.js') }}"></script>
<script src="{{ asset('assets/libs/simplebar/simplebar.min.js') }}"></script>
<script src="{{ asset('assets/libs/node-waves/waves.min.js') }}"></script>
<script src="{{ asset('assets/js/app.js') }}"></script>
<script src="{{ asset('js/clienttimer.js') }}"></script>
<script src="{{ asset('js/layout-shell.js') }}"></script>

@stack('scripts')
</body>
</html>
