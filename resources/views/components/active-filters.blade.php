@php($count = $listing()->activeFilters())
@if ($count > 0)
    <span class="meilifacetsActiveFilters">{{ $count }}</span>
@endif
