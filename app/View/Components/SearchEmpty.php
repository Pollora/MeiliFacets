<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;

final class SearchEmpty extends ContractComponent
{
    public function render(): View
    {
        return view('meilifacets::components.search-empty');
    }
}
