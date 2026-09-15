<fieldset {{ $attributes->class('meilifacetsFacet') }} data-taxonomy="{{ $facet->name }}"
          @if ($bounds()->isEmpty()) hidden @endif {{ $hook('facet') }} {{ $scrollMark() }}>
    <legend @class(['meilifacetsFacetLabel' => ! $facet->namesItself(), 'meilifacetsHidden' => $facet->namesItself()])>
        {{ $facet->label }}
    </legend>
    <div class="meilifacetsFacetPanel" id="{{ $ids->facetPanel($facet->name) }}">
        <div class="meilifacetsFacetPanelInner">
            @if ($showsSlider())
                <x-meilifacets::price.range :label="$facet->label" :readout="$readout()" :fill="$fill()"
                                            :handles="$handles()" :bounds="$bounds()" :money="$money" />
            @endif

            @if ($showsFields())
                <x-meilifacets::price.fields :handles="$handles()" :bounds="$bounds()" :money="$money" />
            @else
                <x-meilifacets::price.hidden :handles="$handles()" />
            @endif
        </div>
    </div>
</fieldset>
