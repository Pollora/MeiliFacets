<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Http\ServiceUnavailable;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\FacetQuery;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShownFilterCountTest extends TestCase
{
    private const string RESULTS = 'results';

    private const string UNFILTERED = 'unfiltered';

    private Facet $brand;

    private Facet $size;

    private PriceFilter $price;

    protected function setUp(): void
    {
        $this->brand = new Facet('product_brand', 'Brand', name: 'brand');
        $this->size = new Facet('pa_size', 'Size', name: 'size');
        $this->price = new PriceFilter('Price');
    }

    #[Test]
    public function it_counts_every_facet_with_a_value_and_the_price_with_a_range(): void
    {
        $listing = $this->listing(new ListingState, [self::RESULTS => [
            'facetDistribution' => [$this->brand->field() => ['acme' => 3], $this->size->field() => ['large' => 2]],
            'facetStats' => $this->priceRange(12.0, 40.0),
        ]]);

        $this->assertSame(3, $this->countPlacing($listing, $this->brand, $this->size, $this->price));
    }

    #[Test]
    public function it_counts_only_what_was_placed_since_the_mark(): void
    {
        $listing = $this->listing(new ListingState, [self::RESULTS => [
            'facetDistribution' => [$this->brand->field() => ['acme' => 3], $this->size->field() => ['large' => 2]],
            'facetStats' => $this->priceRange(12.0, 40.0),
        ]]);
        $listing->placeFacet($this->price, PriceFilter::class);

        $this->assertSame(1, $this->countPlacing($listing, $this->brand));
    }

    #[Test]
    public function it_leaves_out_a_facet_without_values_and_a_price_without_a_range(): void
    {
        $listing = $this->listing(new ListingState, [self::RESULTS => [
            'facetDistribution' => [$this->brand->field() => ['acme' => 3]],
            'facetStats' => $this->priceRange(20.0, 20.0),
        ]]);

        $this->assertSame(1, $this->countPlacing($listing, $this->brand, $this->size, $this->price));
        $this->assertFalse($listing->isShown($this->size));
        $this->assertFalse($listing->isShown($this->price));
    }

    /** `R-49`: a held value stays on screen with no result left, so its facet still counts. */
    #[Test]
    public function it_counts_a_facet_that_holds_a_value_without_results(): void
    {
        $listing = $this->listing(new ListingState(facets: ['product_brand' => ['acme']]), [
            FacetQuery::keyFor('product_brand') => ['facetDistribution' => []],
            self::UNFILTERED => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]],
        ]);

        $this->assertTrue($listing->isShown($this->brand));
        $this->assertSame(1, $this->countPlacing($listing, $this->brand));
    }

    private function countPlacing(ResolvedListing $listing, Facet|PriceFilter ...$filters): int
    {
        $mark = $listing->placedFilterCount();

        foreach ($filters as $filter) {
            $listing->placeFacet($filter, $filter::class);
        }

        return $listing->shownFilterCountSince($mark);
    }

    /**
     * @return array<string, array<string, float>>
     */
    private function priceRange(float $min, float $max): array
    {
        return [
            PriceField::Min->path() => ['min' => $min, 'max' => $max],
            PriceField::Max->path() => ['min' => $min, 'max' => $max],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     */
    private function listing(ListingState $state, array $responses): ResolvedListing
    {
        return new ResolvedListing(
            new FakeListing([$this->brand, $this->size, $this->price]),
            $state,
            new ListingSearch(new FakeSearchEngine($responses), new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
            new UrlParameters([]),
            new ServiceUnavailable,
            new EngineLimits(1000),
        );
    }
}
