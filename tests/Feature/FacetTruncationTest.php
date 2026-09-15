<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Exceptions;
use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\FacetTruncated;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Runs here rather than standalone: `report()` needs the application the Unit
 * suite deliberately does without.
 */
final class FacetTruncationTest extends TestCase
{
    #[Test]
    public function it_speaks_when_a_distribution_stops_on_the_ceiling(): void
    {
        Exceptions::fake();

        $this->listingReturning(400, new EngineLimits(1000, 400))->valuesOf($this->facet());

        Exceptions::assertReported(
            fn (FacetTruncated $cut): bool => str_contains($cut->getMessage(), 'product_brand')
                && str_contains($cut->getMessage(), '400')
        );
    }

    #[Test]
    public function it_says_nothing_of_a_distribution_that_stops_short(): void
    {
        Exceptions::fake();

        $this->listingReturning(399, new EngineLimits(1000, 400))->valuesOf($this->facet());

        Exceptions::assertNotReported(FacetTruncated::class);
    }

    private function facet(): Facet
    {
        return new Facet('product_brand', 'Brand', name: 'brand');
    }

    private function listingReturning(int $values, EngineLimits $limits): ResolvedListing
    {
        $distribution = array_fill_keys(array_map(static fn (int $i): string => 'v'.$i, range(1, $values)), 1);

        return new ResolvedListing(
            new FakeListing([$this->facet()]),
            new ListingState,
            new ListingSearch(
                new FakeSearchEngine(['results' => ['facetDistribution' => ['facets.product_brand' => $distribution]]]),
                new DisjunctiveFacetCounter,
            ),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            new UrlParameters([]),
            new Unavailable,
            $limits,
        );
    }
}
