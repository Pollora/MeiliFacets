<fieldset {{ $attributes->class('meilifacetsFacet') }} data-taxonomy="{{ $facet->taxonomy }}"
          @unless ($hasReadableValues()) hidden @endunless {{ $hook('facet') }} {{ $scrollMark() }}@if ($marksPresentation()) data-presentation="{{ $presentation->slug() }}"@endif>
@if ($collapsible)
    <legend class="meilifacetsFacetLabel"><x-meilifacets::toggle :disclosure="$disclosure()" /></legend>
@else
    <legend class="meilifacetsFacetLabel">{{ $facet->label }}</legend>
@endif
    <div class="meilifacetsFacetPanel" id="{{ $panelId() }}"@if ($collapsible) hidden {{ $hook('panel') }}@endif>
        <div class="meilifacetsFacetPanelInner">
            <ul class="meilifacetsFacetValues">
                @foreach ($values as $value)
                    <li class="meilifacetsFacetValue" @if ($value->folded) hidden @endif {{ $hook('facet-value') }}>
                        <label>
                            <input type="{{ $inputType() }}"
                                   name="{{ $inputName() }}"
                                   value="{{ $value->slug }}"
                                   aria-labelledby="{{ $labelId($value) }}"
                                   aria-describedby="{{ $countId($value) }}"
                                   @checked($value->selected) {{ $hook('input') }}>
                            <span class="meilifacetsFacetName" id="{{ $labelId($value) }}">{{ $value->label }}</span>
                            <span class="meilifacetsFacetCount" id="{{ $countId($value) }}" {{ $hook('count') }}>
                                {{ $countLabel($value) }}
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
            <button type="button" class="meilifacetsFacetMore" aria-expanded="false"
                    @unless ($hasFoldedValues()) hidden @endunless {{ $hook('more') }}>
                <span {{ $hook('more-label') }}>{{ __('Show more') }}</span>
                <span hidden {{ $hook('less-label') }}>{{ __('Show less') }}</span>
            </button>
        </div>
    </div>
</fieldset>
