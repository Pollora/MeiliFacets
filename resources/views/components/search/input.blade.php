<div class="meilifacetsSearchField" {{ $hook('search-field') }}>
    <input id="{{ $inputId }}" type="search" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-label="{{ __('Search') }}" autocomplete="off" {{ $attributes->class('meilifacetsSearchInput') }} {{ $hook('search-input') }}>
</div>
