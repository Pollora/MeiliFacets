<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Search;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\View\Components\SearchComponent;

final class Panel extends SearchComponent
{
    public function render(): View
    {
        return view('meilifacets::components.search.panel', ['panelId' => $this->ids->searchPanel()]);
    }
}
