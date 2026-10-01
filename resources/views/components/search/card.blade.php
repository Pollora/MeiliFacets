<a {{ $attributes->class('meilifacetsSearchCard') }} {{ $link->attributes }}>
    @if ($image->isPresent())
        <img {{ $image->attributes->class('meilifacetsSearchCardImage') }} alt="" loading="lazy" decoding="async">
    @endif
    @if ($title->isPresent())
        <span {{ $title->attributes->class('meilifacetsSearchCardTitle') }}>{{ $title }}</span>
    @endif
    @if ($summary->isPresent())
        <span {{ $summary->attributes->class('meilifacetsSearchCardSummary') }}>{{ $summary }}</span>
    @endif
    @if ($price->isPresent())
        <span {{ $price->attributes->class('meilifacetsSearchCardPrice') }}>{{ $price }}</span>
    @endif
</a>
