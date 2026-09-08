@if ($listing->failed())
    <x-meilifacets::unavailable />
@else
    <p class="meilifacetsResultsEmpty" @unless ($cards === []) hidden @endunless {{ $hook('empty') }}>
        {{ $listing->pagination()->isPastTheEnd() ? __('There is nothing on this page.') : __('No results found.') }}
    </p>

    <ul class="meilifacetsResults" @if ($cards === []) hidden @endif {{ $hook('results') }}>
        @foreach ($cards as $card)
            <li class="meilifacetsResultsItem" {{ $hook('card') }}>
                <x-meilifacets::card :card="$card" :priority="$priority($loop->index)" />
            </li>
        @endforeach
    </ul>

    <template {{ $hook('card-template') }}>
        <li class="meilifacetsResultsItem" {{ $hook('card') }}>
            <x-meilifacets::card :card="[]" />
        </li>
    </template>

    @if ($items && ! $items->isEmpty())
        <script type="application/ld+json">{!! $items->toJson() !!}</script>
    @endif
@endif
