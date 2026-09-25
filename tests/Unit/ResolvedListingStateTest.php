<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Http\Unavailable;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** What the components read of the visitor's state, asked of the listing rather than reached through it. */
final class ResolvedListingStateTest extends TestCase
{
    private Facet $brand;

    private Facet $size;

    protected function setUp(): void
    {
        $this->brand = new Facet('product_brand', 'Brand', name: 'brand');
        $this->size = new Facet('pa_size', 'Size', name: 'size');
    }

    #[Test]
    public function it_answers_the_values_held_in_a_facet(): void
    {
        $listing = $this->listing(new ListingState(facets: ['product_brand' => ['acme', 'zest']]));

        $this->assertSame(['acme', 'zest'], $listing->selectedIn($this->brand));
        $this->assertSame([], $listing->selectedIn($this->size));
    }

    #[Test]
    public function it_answers_the_price_asked_and_counts_it_as_one_filter(): void
    {
        $asked = new Range(10.0, 20.0);
        $listing = $this->listing(new ListingState(price: $asked));

        $this->assertSame($asked, $listing->askedPrice());
        $this->assertSame(1, $listing->priceFilterCount());
        $this->assertSame(0, $this->listing(new ListingState)->priceFilterCount());
    }

    private function listing(ListingState $state): ResolvedListing
    {
        return new ResolvedListing(
            new FakeListing([$this->brand, $this->size]),
            $state,
            new ListingSearch(new FakeSearchEngine, new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            new UrlParameters([]),
            new Unavailable,
            new EngineLimits(1000),
        );
    }
}
