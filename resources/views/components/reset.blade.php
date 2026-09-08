<button type="button" class="meilifacetsReset"
        @if ($listing->isPristine()) hidden @endif {{ $hook('reset') }} {{ $scrollMark() }}>
    {{ __('Clear all') }}
</button>
