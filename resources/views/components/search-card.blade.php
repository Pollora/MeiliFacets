<a {{ $attributes->class('meilifacetsSearchCard') }} href="" {{ $hook('url') }}>
    <img class="meilifacetsSearchCardImage" alt="" loading="lazy" decoding="async" hidden {{ $hook('image') }}>
    <span class="meilifacetsSearchCardTitle" {{ $hook('title') }}></span>
    <span class="meilifacetsSearchCardSummary" hidden {{ $hook('summary') }}></span>
    <span class="meilifacetsSearchCardPrice" hidden {{ $hook('price') }}></span>
</a>
