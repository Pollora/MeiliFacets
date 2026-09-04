<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;

final class Listing extends ListingComponent
{
    public function render(): View
    {
        return view('meilifacets::components.listing');
    }
}
