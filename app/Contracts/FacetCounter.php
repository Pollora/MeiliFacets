<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Contracts;

use Modules\MeiliFacets\Listing\ListingState;

interface FacetCounter
{
    /**
     * Extra searches whose responses carry the counts, keyed by taxonomy.
     *
     * @return array<string, array<string, mixed>>
     */
    public function queries(Listing $listing, ListingState $state): array;
}
