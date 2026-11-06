{{-- Rendered even with nothing to paginate: the client fills these slots, it adds none. --}}
<nav class="meilifacetsPagination" aria-label="{{ __('Pagination') }}"
     @unless ($pagination->hasPages()) hidden @endunless {{ $hook('pagination') }} {{ $scrollMark() }}>
    <a class="meilifacetsPaginationPrevious" href="{{ $linkTo($pagination->previous()) }}"
       @unless ($pagination->hasPrevious()) hidden @endunless {{ $hook('previous') }}>
        {{ __('Previous') }}
    </a>
    @foreach ($pagination->slots() as $number)
        <a class="meilifacetsPaginationPage" @if ($number !== null) href="{{ $linkTo($number) }}" @else hidden @endif
           @if ($number === $pagination->current) aria-current="page" @endif {{ $hook('page') }}>
            {{ $number }}
        </a>
    @endforeach
    <a class="meilifacetsPaginationNext" href="{{ $linkTo($pagination->next()) }}"
       @unless ($pagination->hasNext()) hidden @endunless {{ $hook('next') }}>
        {{ __('Next') }}
    </a>
</nav>
