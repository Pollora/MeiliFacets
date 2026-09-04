@php($resolved = $listing())
@unless ($resolved->state->isPristine())
    <a class="meilifacetsReset" href="{{ $resolved->urls()->reset() }}">
        {{ __('Tout effacer', 'meilifacets') }}
    </a>
@endunless
