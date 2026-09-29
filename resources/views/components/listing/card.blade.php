<article {{ $attributes->class('meilifacetsCard') }}>
    <a class="meilifacetsCardLink" href="{{ $url }}" {{ $hook('url') }}>
        <img class="meilifacetsCardImage" src="{{ $image->src }}" alt="{{ $image->alt }}"
             @if ($image->hasDimensions) width="{{ $image->width }}" height="{{ $image->height }}" @endif
             loading="{{ $image->priority->loading() }}"
             fetchpriority="{{ $image->priority->fetchPriority() }}"
             decoding="async" @unless ($image->isPresent) hidden @endunless {{ $hook('image') }}>
        <{{ $heading->value }} class="meilifacetsCardTitle" {{ $hook('title') }}>{{ $title }}</{{ $heading->value }}>
    </a>
    <p class="meilifacetsCardPrice" @if ($price->isEmpty()) hidden @endif {{ $hook('price') }}>{{ $price }}</p>
</article>
