<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\PriceFilter;

final class Facets extends ListingComponent
{
    public function __construct(CurrentListing $listings, string $name = '', bool $scroll = false, public bool $collapsible = false)
    {
        parent::__construct($listings, $name, $scroll);
    }

    public function shouldRender(): bool
    {
        return $this->listing->remainingFacets() !== [] || $this->listing->applyMode()->needsButton();
    }

    /** The only place that knows which kind renders through what. */
    public function componentFor(Placeable $filter): string
    {
        return match (true) {
            $filter instanceof PriceFilter => 'meilifacets::price',
            default => 'meilifacets::facet',
        };
    }

    public function render(): View
    {
        return view('meilifacets::components.facets', [
            'applyMode' => $this->listing->applyMode()->value,
            'needsApplyButton' => $this->listing->applyMode()->needsButton(),
        ]);
    }
}
