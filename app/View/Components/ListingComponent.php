<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\View\ElementId;

abstract class ListingComponent extends ContractComponent
{
    public ResolvedListing $listing;

    public ElementId $ids;

    public function __construct(CurrentListing $listings, public string $name = '')
    {
        $this->listing = $name === '' ? $listings->sole() : $listings->named($name);
        $this->ids = new ElementId($this->listing->name());
    }
}
