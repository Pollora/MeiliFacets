<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\View\ElementId;

abstract class ListingComponent extends ContractComponent
{
    public function __construct(
        private readonly CurrentListing $listings,
        public string $name = '',
    ) {}

    public function listing(): ResolvedListing
    {
        return $this->name === '' ? $this->listings->sole() : $this->listings->named($this->name);
    }

    public function ids(): ElementId
    {
        return new ElementId($this->listing()->name());
    }
}
