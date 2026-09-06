@php($resolved = $listing())
@if ($resolved->failed())
    <x-meilifacets::unavailable />
@else
    <p class="meilifacetsResultsEmpty" @unless ($resolved->cards() === []) hidden @endunless {{ $hook('empty') }}>
        {{ __('No results found.') }}
    </p>

    <ul class="meilifacetsResults" @if ($resolved->cards() === []) hidden @endif {{ $hook('results') }}>
        @foreach ($resolved->cards() as $card)
            <li class="meilifacetsResultsItem" {{ $hook('card') }}>
                <x-meilifacets::card :card="$card" :priority="$priority($loop->index)" />
            </li>
        @endforeach
    </ul>

    {{-- Cloned by the client: the only source of card markup. --}}
    <template {{ $hook('card-template') }}>
        <li class="meilifacetsResultsItem" {{ $hook('card') }}>
            <x-meilifacets::card :card="[]" />
        </li>
    </template>

    {{-- Null on a page robots are told to skip: nothing structured to publish. --}}
    @php($items = $itemList())
    @if ($items && ! $items->isEmpty())
        <script type="application/ld+json">{!! $items->toJson() !!}</script>
    @endif
@endif
