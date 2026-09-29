<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\View\Components\ListingComponent;

final class Pagination extends ListingComponent
{
    public function render(): View
    {
        return view('meilifacets::components.listing.pagination', ['pagination' => $this->listing->pagination()]);
    }
}
