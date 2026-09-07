<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\Enums\Contract;

final class Listing extends ListingComponent
{
    public function render(): View
    {
        return view('meilifacets::components.listing', ['contract' => Contract::version()]);
    }
}
