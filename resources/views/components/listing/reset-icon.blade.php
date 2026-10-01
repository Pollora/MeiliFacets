<button type="button" class="meilifacetsReset" aria-label="{{ __('Clear all') }}" data-shape="{{ $shape->mark() }}"
        @if ($hasNothingToClear()) hidden @endif {{ $hook('reset') }} {{ $scrollMark() }}>
    @isset($icon)
        @if ($icon->isNotEmpty())<span class="meilifacetsResetIcon" aria-hidden="true">{{ $icon }}</span>@endif
    @else
        <img class="meilifacetsResetIcon" src="{{ $defaultIconUrl() }}" alt="" width="20" height="20" loading="lazy" fetchpriority="low" decoding="async">
    @endisset
</button>
