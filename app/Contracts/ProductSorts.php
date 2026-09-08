<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use Modules\MeiliFacets\Listing\Sort;

interface ProductSorts
{
    /**
     * @return array<string, Sort> key as it travels in the URL, to its expression
     */
    public function all(): array;
}
