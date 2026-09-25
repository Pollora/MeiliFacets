@use(Modules\MeiliFacets\Enums\Hook)
@props(['disclosure'])
<button type="button" class="meilifacetsFacetToggle" aria-expanded="false"
        aria-controls="{{ $disclosure->panelId }}"@isset($disclosure->badge) aria-describedby="{{ $disclosure->badge->id }}"@endisset
        {{ Hook::Toggle->attribute() }}>
    <span class="meilifacetsFacetToggleName"><span class="meilifacetsFacetToggleLabel"@if ($disclosure->labelRestated) aria-hidden="true"@endif>{{ $disclosure->label }}</span>{{ $slot }}</span>
@isset($disclosure->badge)
    <span class="meilifacetsFacetSelected" id="{{ $disclosure->badge->id }}" aria-hidden="true"
          @if ($disclosure->badge->holdsNothing()) hidden @endif {{ Hook::SelectedCount->attribute() }}>{{ $disclosure->badge->text() }}</span>
@endisset
</button>
