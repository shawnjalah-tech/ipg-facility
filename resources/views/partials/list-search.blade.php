<form method="GET" action="{{ url()->current() }}" class="list-search" role="search">
    @foreach(['status', 'role'] as $filter)
        @if(request()->filled($filter))
            <input type="hidden" name="{{ $filter }}" value="{{ request($filter) }}">
        @endif
    @endforeach
    <label for="list-search" class="sr-only">Search</label>
    <input class="form-control" type="search" id="list-search" name="q" value="{{ request('q') }}" maxlength="200" aria-label="Search" autocomplete="off">
    <button class="btn btn-primary" type="submit">Search</button>
    @if(request()->filled('q') || request()->filled('status') || request()->filled('role'))
        <a class="btn btn-outline-secondary" href="{{ url()->current() }}">Clear filters</a>
    @endif
</form>
