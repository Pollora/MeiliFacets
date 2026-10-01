<div {{ $attributes->class('meilifacetsSearchSection') }} data-type="{{ $type->postType }}" data-limit="{{ $limit }}" hidden {{ $hook('search-section') }}>
    <h2 id="{{ $headingId }}" class="meilifacetsSearchHeading">
        {{ $type->heading }}
        <span class="meilifacetsSearchCount" {{ $hook('search-count') }}></span>
    </h2>
    @if ($type->archive !== null)
        <a class="meilifacetsSearchSeeAll" href="{{ $type->archive }}" {{ $hook('search-see-all') }}>{{ $type->seeAllLabel }}</a>
    @endif
    <ul id="{{ $listboxId }}" class="meilifacetsSearchResults" role="listbox" aria-labelledby="{{ $headingId }}" {{ $hook('search-results') }}></ul>
    <template {{ $hook('search-card-template') }}>
        <li class="meilifacetsSearchResult" role="option" {{ $hook('card') }}>
            <x-dynamic-component :component="$type->card" />
        </li>
    </template>
</div>
