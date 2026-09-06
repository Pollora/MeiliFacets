<button type="button" class="meilifacetsReset"
        @if ($listing()->isPristine()) hidden @endif {{ $hook('reset') }}>
    {{ __('Clear all') }}
</button>
