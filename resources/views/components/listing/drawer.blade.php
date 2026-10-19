<div {{ $attributes->class('meilifacetsDrawer') }} id="{{ $drawerId }}" data-media="{{ $media }}" @if ($overflow() !== null) data-overflow="{{ $overflow() }}" @endif {{ $hook('drawer') }}>
    <div class="meilifacetsDrawerSheet" {{ $hook('drawer-sheet') }}>
        <div class="meilifacetsDrawerHead">
            <p class="meilifacetsDrawerTitle" id="{{ $titleId }}" tabindex="-1" {{ $hook('drawer-title') }}>{{ __('Filters') }}</p>
            <button type="button" class="meilifacetsDrawerClose" aria-label="{{ __('Close the filters') }}" {{ $hook('drawer-close') }}>
                <span aria-hidden="true">✕</span>
            </button>
        </div>
        <div class="meilifacetsDrawerHandle" aria-hidden="true" {{ $hook('drawer-close') }}></div>
        <div class="meilifacetsDrawerBody" {{ $hook('drawer-body') }}>
            {{ $slot }}
            @if ($showsMore())
                <button type="button" class="meilifacetsDrawerShowMore" aria-label="{{ __('Show more filters') }}" aria-expanded="false" aria-controls="{{ $drawerId }}" {{ $hook('drawer-show-more') }}>
                    @isset($showMore)
                        @if ($showMore->isNotEmpty())<span class="meilifacetsDrawerIcon" aria-hidden="true">{{ $showMore }}</span>@endif
                    @endisset
                    {{ __('Show more') }}
                </button>
            @endif
        </div>
        @isset($footer)
            <div {{ $footer->attributes->class('meilifacetsDrawerFooter') }} {{ $hook('drawer-footer') }}>
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
