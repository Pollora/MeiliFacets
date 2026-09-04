@php($resolved = $listing())
@unless ($resolved->state->isPristine())
    <a class="meilifacetsReset" href="{{ $resolved->urls()->reset() }}">
        {{ __('Clear all') }}
    </a>
@endunless
