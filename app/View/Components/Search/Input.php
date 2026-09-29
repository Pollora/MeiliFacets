<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Search;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\View\Components\SearchComponent;

final class Input extends SearchComponent
{
    public function render(): View
    {
        return view('meilifacets::components.search.input', ['inputId' => $this->ids->searchInput()]);
    }
}
