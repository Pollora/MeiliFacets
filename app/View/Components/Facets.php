<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\InputType;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;

final class Facets extends ListingComponent
{
    public function inputType(Facet $facet): string
    {
        return InputType::forSelection($facet->selection)->value;
    }

    /**
     * Spelled out rather than left as a bare number beside the label, where a
     * screen reader would read "15ml 2".
     */
    public function countLabel(FacetValue $value): string
    {
        return trans_choice(':count result|:count results', $value->count, ['count' => $value->count]);
    }

    /**
     * @param  list<FacetValue>  $values
     */
    public function hasFoldedValues(array $values): bool
    {
        return array_any($values, static fn (FacetValue $value): bool => $value->folded);
    }

    public function render(): View
    {
        return view('meilifacets::components.facets', [
            'applyMode' => $this->listing->applyMode()->value,
            'needsApplyButton' => $this->listing->applyMode()->needsButton(),
        ]);
    }
}
