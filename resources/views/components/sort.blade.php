@php($choices = $choices())
@if (count($choices) > 1)
    <div class="meilifacetsSort" {{ $hook('sort') }}>
        <label class="meilifacetsSortLabel" id="{{ $id() }}-label" for="{{ $id() }}-trigger">
            {{ __('Sort by') }}
        </label>
        {{-- Labelled by both, so the name read out is "Sort by, Price, low to high". --}}
        <button type="button" class="meilifacetsSortTrigger" id="{{ $id() }}-trigger"
                role="combobox" aria-haspopup="listbox" aria-expanded="false"
                aria-controls="{{ $id() }}-list"
                aria-labelledby="{{ $id() }}-label {{ $id() }}-trigger"
                {{ $hook('sort-trigger') }}>
            {{ $selected()->label }}
        </button>
        <ul class="meilifacetsSortList" id="{{ $id() }}-list" role="listbox"
            aria-labelledby="{{ $id() }}-label" hidden {{ $hook('sort-list') }}>
            @foreach ($choices as $choice)
                <li class="meilifacetsSortOption" id="{{ $choice->id }}" role="option"
                    data-value="{{ $choice->value }}"
                    aria-selected="{{ $choice->selected ? 'true' : 'false' }}"
                    {{ $hook('sort-option') }}>{{ $choice->label }}</li>
            @endforeach
        </ul>
    </div>
@endif
