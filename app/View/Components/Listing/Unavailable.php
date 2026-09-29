<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\View\Components\ContractComponent;

final class Unavailable extends ContractComponent
{
    public function render(): View
    {
        return view('meilifacets::components.listing.unavailable');
    }
}
