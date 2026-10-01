<div id="{{ $panelId }}" role="search" {{ $attributes->class('meilifacetsSearchPanel') }} hidden {{ $hook('search-panel') }}>
    {{ $slot }}
    <p class="meilifacetsSearchStatus" aria-live="polite" {{ $hook('search-status') }}></p>
</div>
