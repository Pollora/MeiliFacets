<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Exceptions;
use Modules\MeiliFacets\Contracts\SearchEngine;
use Modules\MeiliFacets\Http\ServiceUnavailable;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\EngineUnavailable;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Runs here rather than standalone: `report()` needs the application the Unit suite does without. */
final class ListingOutageTest extends TestCase
{
    #[Test]
    public function it_reports_an_engine_that_fails_the_server_render(): void
    {
        Exceptions::fake();

        $listing = $this->listingSearchingWith(new class implements SearchEngine
        {
            public function multiSearch(array $queries): array
            {
                throw EngineUnavailable::unconfigured();
            }
        });

        $this->assertTrue($listing->failed());
        Exceptions::assertReported(EngineUnavailable::class);
    }

    private function listingSearchingWith(SearchEngine $engine): ResolvedListing
    {
        return new ResolvedListing(
            new FakeListing([]),
            new ListingState,
            new ListingSearch($engine, new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            new UrlParameters([]),
            new ServiceUnavailable,
            new EngineLimits(EngineLimits::DEFAULT_REACHABLE_HITS, EngineLimits::DEFAULT_MAX_FACET_VALUES),
        );
    }
}
