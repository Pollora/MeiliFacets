<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\View\Components\ListingComponent;

final class Facets extends ListingComponent
{
    public function __construct(
        CurrentListing $listings,
        string $name = '',
        bool $scroll = false,
        public bool $collapsible = false,
        public bool $withApply = true,
    ) {
        parent::__construct($listings, $name, $scroll);
    }

    public function shouldRender(): bool
    {
        return $this->listing->remainingFacets() !== [] || $this->rendersApply();
    }

    /** The only place that knows which kind renders through what. */
    public function componentFor(Placeable $filter): string
    {
        return match (true) {
            $filter instanceof PriceFilter => 'meilifacets::listing.price',
            default => 'meilifacets::listing.facet',
        };
    }

    public function render(): View
    {
        return view('meilifacets::components.listing.facets', [
            'applyMode' => $this->listing->applyMode()->value,
            'needsApplyButton' => $this->rendersApply(),
        ]);
    }

    private function rendersApply(): bool
    {
        return $this->withApply && $this->listing->applyMode()->needsButton();
    }
}
