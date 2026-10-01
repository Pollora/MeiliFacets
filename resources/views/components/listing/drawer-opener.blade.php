<button type="button" {{ $attributes->class('meilifacetsDrawerOpen') }} aria-expanded="false" aria-controls="{{ $drawerId }}" aria-describedby="{{ $badge->id }}" {{ $hook('drawer-open') }}>
    @isset($icon)
        @if ($icon->isNotEmpty())<span class="meilifacetsDrawerIcon" aria-hidden="true">{{ $icon }}</span>@endif
    @else
        <span class="meilifacetsDrawerIcon" aria-hidden="true"><img src="{{ $defaultIconUrl() }}" alt="" width="14" height="14"></span>
    @endisset
    {{ __('Filters') }}
    <span class="meilifacetsDrawerCount" id="{{ $badge->id }}" aria-hidden="true" @if ($badge->holdsNothing()) hidden @endif {{ $hook('active-count') }}>{{ $badge->text() }}</span>
</button>
