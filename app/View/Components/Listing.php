<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Modules\MeiliFacets\Enums\Contract;

final class Listing extends ListingComponent
{
    public function contract(): HtmlString
    {
        return Contract::version();
    }

    public function render(): View
    {
        return view('meilifacets::components.listing');
    }
}
