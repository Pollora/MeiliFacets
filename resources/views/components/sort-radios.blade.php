<fieldset class="meilifacetsFacet meilifacetsSortChoices" {{ $hook('sort-choices') }} {{ $scrollMark() }}>
@if ($collapsible)
    <legend class="meilifacetsFacetLabel"><x-meilifacets::toggle :disclosure="$disclosure()"><span class="meilifacetsSortSummary">{{ $summary->lead }}<span class="meilifacetsSortChoice" {{ $hook('sort-chosen') }}>{{ $summary->choice }}</span>{{ $summary->trail }}</span></x-meilifacets::toggle></legend>
@else
    <legend class="meilifacetsFacetLabel">{{ $summary->label }}</legend>
@endif
    <div class="meilifacetsFacetPanel" id="{{ $panelId() }}"@if ($collapsible) hidden {{ $hook('panel') }}@endif>
        <ul class="meilifacetsFacetValues">
            @foreach ($choices as $choice)
                <li class="meilifacetsFacetValue" @if ($choice->hidden) hidden @endif {{ $hook('sort-choice-row') }}>
                    <label>
                        <input type="radio" name="{{ $choiceName() }}" value="{{ $choice->value }}" data-label="{{ $choice->label }}"
                               @checked($choice->selected) {{ $hook('sort-choice') }}>
                        <span class="meilifacetsFacetName">{{ $choice->label }}</span>
                    </label>
                </li>
            @endforeach
        </ul>
    </div>
</fieldset>
