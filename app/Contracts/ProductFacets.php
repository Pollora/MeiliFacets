<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use Modules\MeiliFacets\Listing\Facet;

interface ProductFacets
{
    /**
     * @return list<Facet>
     */
    public function all(): array;
}
