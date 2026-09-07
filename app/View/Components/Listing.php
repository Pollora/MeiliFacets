<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Listing\CurrentListing;
use Modules\MeiliFacets\View\ListingScript;

final class Listing extends ListingComponent
{
    public function __construct(CurrentListing $listings, ListingScript $script, string $name = '')
    {
        parent::__construct($listings, $name);

        $script->require($this->listing);
    }

    public function render(): View
    {
        return view('meilifacets::components.listing', ['contract' => Contract::version()]);
    }
}
