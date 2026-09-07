<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;

final class ActiveFilters extends ListingComponent
{
    public function render(): View
    {
        $count = $this->listing->activeFilterCount();

        return view('meilifacets::components.active-filters', [
            'count' => $count,
            'label' => trans_choice(':count active filter|:count active filters', $count),
        ]);
    }
}
