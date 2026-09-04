@php($resolved = $listing())
@php($sorts = $resolved->listing->sorts())
@if ($sorts !== [])
    <nav class="meilifacetsSort" aria-label="{{ __('Sort') }}">
        @foreach ($sorts as $key => $sortOption)
            <a class="meilifacetsSortOption"
               href="{{ $resolved->urls()->sort($resolved->state, $key) }}"
               @if ($resolved->state->sort === $key) aria-current="true" @endif>
                {{ $sortOption->label }}
            </a>
        @endforeach
    </nav>
@endif
