<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;

final class ActiveFilters extends ListingComponent
{
    public function render(): View
    {
        return view('meilifacets::components.active-filters', ['count' => $this->listing->activeFilterCount()]);
    }
}
