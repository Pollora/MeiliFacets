<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\Listing\ProductListing;
use Modules\MeiliFacets\Listing\ResolvedListing;

abstract class ListingComponent extends ContractComponent
{
    public function __construct(public string $name = ProductListing::NAME) {}

    public function listing(): ResolvedListing
    {
        return app(CurrentListing::class)->named($this->name);
    }
}
