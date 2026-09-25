<button type="button" class="meilifacetsReset"@if ($shape->mark() !== null) data-shape="{{ $shape->mark() }}"@endif
        @if ($hasNothingToClear()) hidden @endif {{ $hook('reset') }} {{ $scrollMark() }}>
    {{ __('Clear all') }}
</button>
