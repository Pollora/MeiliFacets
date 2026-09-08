<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use Modules\MeiliFacets\Listing\FacetValue;

interface ValueOrder
{
    /**
     * Negative when the first value reads before the second, zero when the two
     * may keep the order the engine gave them.
     */
    public function compare(FacetValue $first, FacetValue $second): int;
}
