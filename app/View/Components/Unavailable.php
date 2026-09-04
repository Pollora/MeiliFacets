<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

final class Unavailable extends Component
{
    public function render(): View
    {
        return view('meilifacets::components.unavailable');
    }
}
