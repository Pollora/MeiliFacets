<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use BackedEnum;
use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\InputType;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\Facet as Declaration;
use Modules\MeiliFacets\Listing\FacetValue;

final class Facet extends ListingComponent
{
    public Declaration $facet;

    /** @var list<FacetValue> */
    public array $values;

    public function __construct(
        CurrentListing $listings,
        Declaration|BackedEnum|string $facet,
        string $name = '',
    ) {
        parent::__construct($listings, $name);

        if ($facet instanceof Declaration) {
            $this->facet = $facet;
            $this->listing->place($facet);
        } else {
            $this->facet = $this->listing->facetNamed($facet instanceof BackedEnum ? (string) $facet->value : $facet);
            $this->listing->placeApart($this->facet);
        }

        $this->values = $this->listing->valuesOf($this->facet);
    }

    public function inputType(): string
    {
        return InputType::forSelection($this->facet->selection)->value;
    }

    /**
     * Spelled out rather than left as a bare number beside the label, where a
     * screen reader would read "15ml 2".
     */
    public function countLabel(FacetValue $value): string
    {
        return trans_choice(':count result|:count results', $value->count, ['count' => $value->count]);
    }

    public function hasFoldedValues(): bool
    {
        return array_any($this->values, static fn (FacetValue $value): bool => $value->folded);
    }

    public function render(): View
    {
        return view('meilifacets::components.facet');
    }
}
