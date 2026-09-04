<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Listing\Facet;

final class Facets extends ListingComponent
{
    public function inputType(Facet $facet): string
    {
        return $facet->selection->allowsSeveralValues() ? 'checkbox' : 'radio';
    }

    public function inputName(Facet $facet): string
    {
        return $this->listing()->urls()->parameterFor($facet->taxonomy);
    }

    public function render(): View
    {
        return view('meilifacets::components.facets');
    }
}
