<button type="button" {{ $attributes->class('meilifacetsApply') }} aria-describedby="{{ $badge->id }}"@if ($onlyInSheet()) data-only="sheet"@endif {{ $hook('apply') }}>
    {{ __('Apply') }}
    <span class="meilifacetsApplyCount" id="{{ $badge->id }}" aria-hidden="true" @if ($badge->holdsNothing()) hidden @endif {{ $hook('active-count') }}>{{ $badge->text() }}</span>
</button>
