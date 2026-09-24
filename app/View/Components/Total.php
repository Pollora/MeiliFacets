<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\CountLabel;

final class Total extends ListingComponent
{
    public function __construct(CurrentListing $listings, private readonly CountLabel $countLabel, string $name = '')
    {
        parent::__construct($listings, $name);
    }

    public function render(): View
    {
        return view('meilifacets::components.total', [
            'label' => $this->countLabel->of(__(':count item|:count items'), $this->listing->total()),
        ]);
    }
}
