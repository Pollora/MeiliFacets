<form {{ $attributes->class('meilifacetsListingSearch') }} role="search" method="get" action="{{ $action }}" aria-label="{{ $label() }}" {{ $hook('listing-search') }}>
    @foreach ($kept as $field)
        <input type="hidden" name="{{ $field->name }}" value="{{ $field->value }}">
    @endforeach
    <input id="{{ $inputId }}" class="meilifacetsListingSearchInput" type="search" name="{{ $parameter }}" value="{{ $term }}" aria-label="{{ $label() }}" placeholder="{{ $label() }}" maxlength="{{ $maxLength }}" autocomplete="off" enterkeyhint="search" {{ $hook('listing-search-input') }}>
    <button type="button" class="meilifacetsListingSearchClear" aria-label="{{ __('Clear the search') }}" @if ($term === '') hidden @endif {{ $hook('listing-search-clear') }}>
        <span aria-hidden="true">✕</span>
    </button>
</form>
