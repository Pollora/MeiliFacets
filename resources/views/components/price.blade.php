<fieldset {{ $attributes->class('meilifacetsFacet') }} data-taxonomy="{{ $facet->name }}"
          @if ($bounds()->isEmpty()) hidden @endif {{ $hook('facet') }} {{ $scrollMark() }}>
@if ($collapsible)
    <legend class="meilifacetsFacetLabel"><x-meilifacets::toggle :disclosure="$disclosure()" /></legend>
@else
    <legend @class(['meilifacetsFacetLabel' => ! $facet->namesItself(), 'meilifacetsHidden' => $facet->namesItself()])>
        {{ $facet->label }}
    </legend>
@endif
    <div class="meilifacetsFacetPanel" id="{{ $panelId() }}"@if ($collapsible) hidden {{ $hook('panel') }}@endif>
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
