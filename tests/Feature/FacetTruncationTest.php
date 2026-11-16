<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Feature;

use Illuminate\Support\Facades\Exceptions;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\FacetTruncated;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Tests\TestCase;

trait FacetTruncationSupport
{
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

            $limits,
        );
    }
}

/**
 * Runs here rather than standalone: `report()` needs the application the Unit
 * suite deliberately does without.
 */
uses(TestCase::class, FacetTruncationSupport::class);

it('speaks when a distribution stops on the ceiling', function (): void {
    Exceptions::fake();

    $this->listingReturning(400, new EngineLimits(1000, 400))->valuesOf($this->facet());

    Exceptions::assertReported(
        fn (FacetTruncated $cut): bool => str_contains($cut->getMessage(), 'product_brand')
            && str_contains($cut->getMessage(), '400')
    );
});

it('says nothing of a distribution that stops short', function (): void {
    Exceptions::fake();

    $this->listingReturning(399, new EngineLimits(1000, 400))->valuesOf($this->facet());

    Exceptions::assertNotReported(FacetTruncated::class);
});
