<header {{ $attributes->class(['page-header']) }}>
    <div class="page-header-main">
        <h1 class="page-heading">{{ $title }}</h1>
        @isset($context)
            <div class="page-record-context">{{ $context }}</div>
        @endisset
    </div>
    @isset($actions)
        <div class="page-header-actions">{{ $actions }}</div>
    @endisset
</header>
