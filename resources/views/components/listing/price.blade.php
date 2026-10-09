<fieldset {{ $attributes->class('meilifacetsFacet') }} data-filter="{{ $filter->name }}"
          @unless ($isShown()) hidden @endunless {{ $hook('facet') }} {{ $scrollMark() }}>
@if ($collapsible)
    <legend class="meilifacetsFacetLabel"><x-meilifacets::listing.toggle :disclosure="$disclosure()" /></legend>
@else
    <legend @class(['meilifacetsFacetLabel' => ! $filter->namesItself(), 'meilifacetsHidden' => $filter->namesItself()])>
        {{ $filter->label }}
    </legend>
@endif
    <div class="meilifacetsFacetPanel" id="{{ $panelId() }}"@if ($collapsible) hidden {{ $hook('panel') }}@endif>
        <div class="meilifacetsFacetPanelInner">
            @if ($showsSlider())
                <x-meilifacets::listing.price.range :label="$filter->label" :readout="$readout()" :fill="$fill()"
                                            :handles="$handles()" :bounds="$bounds()" :money="$money" />
            @endif

            @if ($showsFields())
                <x-meilifacets::listing.price.fields :handles="$handles()" :bounds="$bounds()" :money="$money" />
            @else
                <x-meilifacets::listing.price.hidden :handles="$handles()" />
            @endif
        </div>
    </div>
</fieldset>
