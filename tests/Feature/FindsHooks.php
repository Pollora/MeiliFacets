<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Modules\MeiliFacets\Enums\Contract;
use Modules\MeiliFacets\Enums\Hook;

/** The selector the browser client builds for a hook: `Contract.selector()` in `shared/contract.ts`. */
trait FindsHooks
{
    private function hooked(Hook $hook): string
    {
        return '['.Contract::Attribute->value.'="'.$hook->value.'"]';
    }
}
