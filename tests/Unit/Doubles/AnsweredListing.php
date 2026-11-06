<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\Doubles;

use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;

/** A listing of 16 cards a page. */
final class AnsweredListing
{
    public static function withTotal(int $total = 0, ListingState $state = new ListingState): ResolvedListing
    {
        return self::searching(new FakeSearchEngine(['results' => ['totalHits' => $total]]), $state);
    }

    public static function unavailable(): ResolvedListing
    {
        return self::searching(new UnavailableSearchEngine, new ListingState);
    }

    private static function searching(SearchEngine $engine, ListingState $state): ResolvedListing
    {
        return new ResolvedListing(
            new FakeListing,
            $state,
            new ListingSearch($engine, new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            new EngineLimits(1000, 400),
        );
    }
}
