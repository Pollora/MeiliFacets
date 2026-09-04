@php($resolved = $listing())
@php($pagination = $resolved->pagination())
@if ($pagination->pages() > 1)
    <nav class="meilifacetsPagination" aria-label="{{ __('Pagination') }}">
        @if ($pagination->hasPrevious())
            <a class="meilifacetsPaginationPrevious" rel="prev"
               href="{{ $resolved->urls()->page($resolved->state, $pagination->previous()) }}">
                {{ __('Previous') }}
            </a>
        @endif
        @foreach ($pagination->numbers() as $number)
            <a class="meilifacetsPaginationPage"
               href="{{ $resolved->urls()->page($resolved->state, $number) }}"
               @if ($number === $pagination->current) aria-current="page" @endif>
                {{ $number }}
            </a>
        @endforeach
        @if ($pagination->hasNext())
            <a class="meilifacetsPaginationNext" rel="next"
               href="{{ $resolved->urls()->page($resolved->state, $pagination->next()) }}">
                {{ __('Next') }}
            </a>
        @endif
    </nav>
@endif
