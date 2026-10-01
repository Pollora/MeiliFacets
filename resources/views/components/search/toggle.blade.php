<button type="button" {{ $attributes->class('meilifacetsSearchToggle') }} aria-expanded="false" aria-controls="{{ $panelId }}" aria-label="{{ __('Search') }}" {{ $hook('search-toggle') }}>
    @isset($icon)
        @if ($icon->isNotEmpty())<span class="meilifacetsSearchIcon" aria-hidden="true">{{ $icon }}</span>@endif
    @else
        <span class="meilifacetsSearchIcon" aria-hidden="true"><img src="{{ $defaultIconUrl() }}" alt="" width="20" height="20"></span>
    @endisset
</button>
