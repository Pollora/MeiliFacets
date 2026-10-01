<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\ActiveValueList;
use Modules\MeiliFacets\View\Components\ListingComponent;

final class ActiveValues extends ListingComponent
{
    public function __construct(CurrentListing $listings, private readonly ActiveValueList $values, string $name = '')
    {
        parent::__construct($listings, $name);
    }

    public function render(): View
    {
        return view('meilifacets::components.listing.active-values', [
            'values' => $this->values->of($this->listing),
        ]);
    }
}
