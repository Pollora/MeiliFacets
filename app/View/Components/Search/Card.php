<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components\Search;

use Illuminate\Contracts\View\View;
use Modules\MeiliFacets\View\Components\ContractComponent;

final class Card extends ContractComponent
{
    public function render(): View
    {
        return view('meilifacets::components.search.card');
    }
}
