@php($count = $listing()->activeFilterCount())
<span class="meilifacetsActiveFilters" @if ($count === 0) hidden @endif {{ $hook('active-filters') }}>{{ $count }}</span>
