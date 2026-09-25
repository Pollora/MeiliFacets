@use(Modules\MeiliFacets\Enums\Hook)
@props(['disclosure'])
<button type="button" class="meilifacetsFacetToggle" aria-expanded="false"
        aria-controls="{{ $disclosure->panelId }}" aria-describedby="{{ $disclosure->selectedCountId }}"
        {{ Hook::Toggle->attribute() }}>
    <span class="meilifacetsFacetToggleName"><span class="meilifacetsFacetToggleLabel">{{ $disclosure->label }}</span>{{ $slot }}</span>
    <span class="meilifacetsFacetSelected" id="{{ $disclosure->selectedCountId }}" aria-hidden="true"
          @if ($disclosure->holdsNothing()) hidden @endif {{ Hook::SelectedCount->attribute() }}>{{ $disclosure->badge() }}</span>
</button>
