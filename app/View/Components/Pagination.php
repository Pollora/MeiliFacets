<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;

final class Pagination extends ListingComponent
{
    public function render(): View
    {
        return view('meilifacets::components.pagination', ['pagination' => $this->listing->pagination()]);
    }
}
