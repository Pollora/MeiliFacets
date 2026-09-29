<button type="button" {{ $attributes->class('meilifacetsApply') }}@if ($shape->showsCount()) aria-describedby="{{ $badge->id }}"@endif @if ($shape->mark() !== null) data-shape="{{ $shape->mark() }}"@endif @if ($onlyInSheet()) data-only="sheet"@endif {{ $hook('apply') }}>
    {{ $label() }}
@if ($shape->showsCount())
    <span class="meilifacetsApplyCount" id="{{ $badge->id }}" aria-hidden="true" @if ($badge->holdsNothing()) hidden @endif {{ $hook('active-count') }}>{{ $badge->text() }}</span>
@endif
</button>
