<button type="button" {{ $attributes->class('meilifacetsApply') }} aria-describedby="{{ $countId }}"@if ($onlyInSheet()) data-only="sheet"@endif {{ $hook('apply') }}>
    {{ __('Apply') }}
    <span class="meilifacetsApplyCount" id="{{ $countId }}" aria-hidden="true" @if ($holdsNothing) hidden @endif {{ $hook('active-count') }}>{{ $badge }}</span>
</button>
