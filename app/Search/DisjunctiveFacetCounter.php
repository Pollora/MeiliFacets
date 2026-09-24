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
            $query = new FacetQuery($facet);

            if ($query->isMeasuredApart($state)) {
                $queries[$facet->taxonomy] = QueryPlan::apart($listing, $state, $query);
            }
        }

        return $queries;
    }
}
