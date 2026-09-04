<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Contracts\FacetCounter;
use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Listing\ListingState;

/**
 * Counts read off `facetDistribution`, which is not deduplicated per product:
 * a product with several variations sharing a value is counted twice.
 */
final readonly class DisjunctiveFacetCounter implements FacetCounter
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function queries(Listing $listing, ListingState $state): array
    {
        $queries = [];

        foreach ($listing->facets() as $facet) {
            if (QueryPlan::isCountedApart($facet, $state)) {
                $queries[$facet->taxonomy] = QueryPlan::counting($listing, $state, $facet);
            }
        }

        return $queries;
    }
}
