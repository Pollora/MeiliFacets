<article {{ $attributes->class('meilifacetsCard') }}>
    <a {{ $link->attributes->class('meilifacetsCardLink') }}>
        @if ($image->isPresent())
            <img {{ $image->attributes->class('meilifacetsCardImage') }} decoding="async">
        @endif
        @if ($title->isPresent())
            <{{ $heading }} {{ $title->attributes->class('meilifacetsCardTitle') }}>{{ $title }}</{{ $heading }}>
        @endif
    </a>
    @if ($price->isPresent())
        <p {{ $price->attributes->class('meilifacetsCardPrice') }}>{{ $price }}</p>
    @endif
</article>
