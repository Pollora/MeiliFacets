<div class="meilifacetsUnavailable" role="alert" @if ($whenSearchFails) hidden {{ $hook('unavailable') }} @endif>
    <p>{{ __('Search is temporarily unavailable. Please try again in a moment.') }}</p>
</div>
