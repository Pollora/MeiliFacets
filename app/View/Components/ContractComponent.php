<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\View\Components;

use Illuminate\Support\HtmlString;
use Illuminate\View\Component;
use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;

abstract class ContractComponent extends Component
{
    public bool $scroll = false;

    public function hook(string $name): HtmlString
    {
        return Hook::from($name)->attribute();
    }

    public function scrollMark(): HtmlString
    {
        return new HtmlString($this->scroll ? Contract::SCROLL_ATTRIBUTE : '');
    }
}
