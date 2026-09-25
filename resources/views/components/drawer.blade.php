<div {{ $attributes->class('meilifacetsDrawer') }} id="{{ $drawerId }}" data-media="{{ $media }}" {{ $hook('drawer') }}>
    <div class="meilifacetsDrawerSheet">
        <div class="meilifacetsDrawerHead">
            <{{ $heading->value }} class="meilifacetsDrawerTitle" id="{{ $titleId }}" tabindex="-1" {{ $hook('drawer-title') }}>{{ __('Filters') }}</{{ $heading->value }}>
            <button type="button" class="meilifacetsDrawerClose" aria-label="{{ __('Close the filters') }}" {{ $hook('drawer-close') }}>
                <span aria-hidden="true">✕</span>
            </button>
        </div>
        <div class="meilifacetsDrawerHandle" aria-hidden="true" {{ $hook('drawer-close') }}></div>
        <div class="meilifacetsDrawerBody">
            {{ $slot }}
        </div>
        @isset($footer)
            <div {{ $footer->attributes->class('meilifacetsDrawerFooter') }}>
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
