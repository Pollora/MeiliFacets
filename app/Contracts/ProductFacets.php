<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\PriceFilter;

interface ProductFacets
{
    /**
     * @return list<Facet|PriceFilter>
     */
    public function all(): array;
}
