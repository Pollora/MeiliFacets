<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Modules\MeiliFacets\Enums\Hook;

abstract class ContractComponent extends Component
{
    public function hook(string $name): HtmlString
    {
        return Hook::from($name)->attribute();
    }
}
