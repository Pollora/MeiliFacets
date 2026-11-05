<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Listing;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\View\Components\ContractComponent;

final class Unavailable extends ContractComponent
{
    /** Rendered hidden beside the results: the client reveals it when a later search fails. */
    public function __construct(public bool $whenSearchFails = false) {}

    public function render(): View
    {
        return view('meilifacets::components.listing.unavailable');
    }
}
