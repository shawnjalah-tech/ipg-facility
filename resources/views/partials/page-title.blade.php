@hasSection('page-title')
    <div class="page-title-box">
        <h4 class="page-title">@yield('page-title')</h4>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Home</a></li>
            @yield('breadcrumb')
        </ol>
    </div>
@endif