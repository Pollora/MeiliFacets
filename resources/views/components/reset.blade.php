<button type="button" class="meilifacetsReset"
        @if ($listing()->state->isDefault()) hidden @endif {{ $hook('reset') }}>
    {{ __('Clear all') }}
</button>
