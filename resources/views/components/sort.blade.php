@if (count($choices) > 1)
    <div class="meilifacetsSort" {{ $hook('sort') }}>
        <label class="meilifacetsSortLabel" id="{{ $ids->sortLabel() }}" for="{{ $ids->sortTrigger() }}">
            {{ __('Sort by') }}
        </label>
        {{-- Labelled by both, so the name read out is "Sort by, Price, low to high". --}}
        <button type="button" class="meilifacetsSortTrigger" id="{{ $ids->sortTrigger() }}"
                role="combobox" aria-haspopup="listbox" aria-expanded="false"
                aria-controls="{{ $ids->sortList() }}"
                aria-labelledby="{{ $ids->sortLabel() }} {{ $ids->sortTrigger() }}"
                {{ $hook('sort-trigger') }}>
            {{ $selected->label }}
        </button>
        <ul class="meilifacetsSortList" id="{{ $ids->sortList() }}" role="listbox"
            aria-labelledby="{{ $ids->sortLabel() }}" hidden {{ $hook('sort-list') }}>
            @foreach ($choices as $choice)
                <li class="meilifacetsSortOption" id="{{ $choice->id }}" role="option"
                    data-value="{{ $choice->value }}"
                    aria-selected="{{ $choice->selected ? 'true' : 'false' }}"
                    {{ $hook('sort-option') }}>{{ $choice->label }}</li>
            @endforeach
        </ul>
    </div>
@endif
