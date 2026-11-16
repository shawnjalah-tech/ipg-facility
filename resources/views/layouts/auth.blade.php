<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Login') — {{ config('app.name') }}</title>

    <link href="{{ asset('assets/fonts/poppins.css') }}" rel="stylesheet">

    <link href="{{ asset('assets/css/bootstrap.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/icons.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/app-style.css') }}" rel="stylesheet">

    <link href="{{ asset('css/auth.css') }}" rel="stylesheet">
</head>
<body class="auth-body">

<main class="auth-main">
    <div class="auth-card">
        <a href="{{ route('login') }}" class="auth-brand">{{ config('app.name') }}</a>
        @yield('content')
    </div>
</main>

<script>
(function () {
    // Password visibility toggle
    document.querySelectorAll('.field-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.parentElement.querySelector('input');
            if (!input) return;
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.classList.toggle('is-visible', show);
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.setAttribute('aria-pressed', String(show));
        });
    });

    window.addEventListener('pageshow', function () {
        document.querySelectorAll('.btn-auth').forEach(function (btn) {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-busy');
        });
    });

    // Submit loading state
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('.btn-auth');
            if (btn && !btn.disabled) {
                btn.classList.add('is-loading');
                btn.setAttribute('aria-busy', 'true');
                btn.disabled = true;
            }
        });
    });
})();
</script>

</body>
</html>
