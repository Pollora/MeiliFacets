<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;

trait BuildsResolvedListings
{
    private function resolved(
        ?Listing $listing = null,
        ?ListingState $state = null,
        ?SearchEngine $engine = null,
        int $reachableHits = 1000,
        int $maxFacetValues = 100,
    ): ResolvedListing {
        return new ResolvedListing(
            $listing ?? new FakeListing,
            $state ?? new ListingState,
            new ListingSearch($engine ?? new FakeSearchEngine, new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            new EngineLimits($reachableHits, $maxFacetValues),
        );
    }
}
