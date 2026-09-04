@php($resolved = $listing())
@if ($resolved->failed())
    <x-meilifacets::unavailable />
@elseif ($resolved->cards() === [])
    <p class="meilifacetsResultsEmpty">
        {{ __('Aucun résultat n’a été trouvé.', 'meilifacets') }}
    </p>
@else
    <ul class="meilifacetsResults">
        @foreach ($resolved->cards() as $card)
            <li class="meilifacetsResultsItem">
                <x-meilifacets::card :card="$card" :rank="$loop->index" />
            </li>
        @endforeach
    </ul>

    {{-- Null on a page robots are told to skip: nothing structured to publish. --}}
    @php($items = $itemList())
    @if ($items && ! $items->isEmpty())
        <script type="application/ld+json">{!! $items->toJson() !!}</script>
    @endif
@endif
