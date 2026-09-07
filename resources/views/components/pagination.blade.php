{{-- Rendered even with nothing to paginate: the client fills these slots, it adds none. --}}
<nav class="meilifacetsPagination" aria-label="{{ __('Pagination') }}"
     @unless ($pagination->hasPages()) hidden @endunless {{ $hook('pagination') }}>
    <button type="button" class="meilifacetsPaginationPrevious" value="{{ $pagination->previous() }}"
            @unless ($pagination->hasPrevious()) hidden @endunless {{ $hook('previous') }}>
        {{ __('Previous') }}
    </button>
    @foreach ($pagination->slots() as $number)
        <button type="button" class="meilifacetsPaginationPage" value="{{ $number }}"
                @if ($number === null) hidden @endif
                @if ($number === $pagination->current) aria-current="page" @endif {{ $hook('page') }}>
            {{ $number }}
        </button>
    @endforeach
    <button type="button" class="meilifacetsPaginationNext" value="{{ $pagination->next() }}"
            @unless ($pagination->hasNext()) hidden @endunless {{ $hook('next') }}>
        {{ __('Next') }}
    </button>
</nav>
