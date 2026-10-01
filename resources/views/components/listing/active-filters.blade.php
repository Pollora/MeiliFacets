{{-- The words, not just the digit: a bare number names nothing, on screen or to a screen reader. --}}
<span class="meilifacetsActiveFilters" @if ($count === 0) hidden @endif {{ $hook('active-filters') }}>{{ $label }}</span>
