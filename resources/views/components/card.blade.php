<article {{ $attributes->class('meilifacetsCard') }}>
    <a class="meilifacetsCardLink" href="{{ $url }}">
        <img class="meilifacetsCardImage" src="{{ $image->src }}" alt="{{ $image->alt }}"
             @if ($image->hasDimensions) width="{{ $image->width }}" height="{{ $image->height }}" @endif
             loading="{{ $image->priority->loading() }}"
             fetchpriority="{{ $image->priority->fetchPriority() }}"
             decoding="async" @unless ($image->isPresent) hidden @endunless>
        <{{ $heading->value }} class="meilifacetsCardTitle">{{ $title }}</{{ $heading->value }}>
    </a>
    <p class="meilifacetsCardPrice" @if ($price->isEmpty()) hidden @endif>{{ $price }}</p>
</article>
