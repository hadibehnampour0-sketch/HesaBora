@if ($canFinancial && $standaloneLinks->isNotEmpty())
    <nav aria-label="{{ __('Quick Access') }}" class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($standaloneLinks as $link)
            @include('home._quick-access-link', compact('link'))
        @endforeach
    </nav>
@endif
