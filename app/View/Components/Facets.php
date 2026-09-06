<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;

final class Facets extends ListingComponent
{
    public function inputType(Facet $facet): string
    {
        return $facet->selection->allowsSeveralValues() ? 'checkbox' : 'radio';
    }

    public function inputName(Facet $facet): string
    {
        return $this->listing()->parameterFor($facet->taxonomy);
    }

    /**
     * Spelled out rather than left as a bare number beside the label, where a
     * screen reader would read "15ml 2".
     */
    public function countLabel(FacetValue $value): string
    {
        return trans_choice(':count result|:count results', $value->count, ['count' => $value->count]);
    }

    public function countId(Facet $facet, FacetValue $value): string
    {
        return $this->ids()->facetCount($facet->taxonomy, $value->slug);
    }

    public function render(): View
    {
        return view('meilifacets::components.facets');
    }
}
