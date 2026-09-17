<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\CountLabel;

final class ActiveFilters extends ListingComponent
{
    public function __construct(CurrentListing $listings, private readonly CountLabel $countLabel, string $name = '', bool $scroll = false)
    {
        parent::__construct($listings, $name, $scroll);
    }

    public function render(): View
    {
        $count = $this->listing->activeFilterCount();

        return view('meilifacets::components.active-filters', [
            'count' => $count,
            'label' => $this->countLabel->of(__(':count active filter|:count active filters'), $count),
        ]);
    }
}
