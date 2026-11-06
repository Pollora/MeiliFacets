<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\ResolvedListing;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\FacetQuery;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CatalogFilterCountTest extends TestCase
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
        $listing->placement()->placeFilter($this->price, PriceFilter::class);

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

    /** Filtered or not, the row holds the same filters: a cleared filter in the browser never moves it back. */
    #[Test]
    public function it_counts_a_facet_the_filters_emptied_but_the_catalog_offers(): void
    {
        $listing = $this->listing(new ListingState(facets: ['product_brand' => ['acme']]), [
            self::RESULTS => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]], 'facetStats' => $this->priceRange(20.0, 20.0)],
            self::UNFILTERED => ['facetDistribution' => [$this->brand->field() => ['acme' => 3], $this->size->field() => ['large' => 2]]],
        ]);

        $this->assertFalse($listing->isShown($this->size));
        $this->assertSame(3, $this->countPlacing($listing, $this->brand, $this->size, $this->price));
    }

    #[Test]
    public function it_leaves_out_a_facet_the_whole_catalog_leaves_empty(): void
    {
        $listing = $this->listing(new ListingState(facets: ['product_brand' => ['acme']]), [
            self::RESULTS => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]],
            self::UNFILTERED => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]],
        ]);

        $this->assertSame(1, $this->countPlacing($listing, $this->brand, $this->size));
    }

    /** On a category page the flat category facet is placed but never searched: no tick may bring it into the row. */
    #[Test]
    public function it_leaves_out_a_facet_the_listing_does_not_measure_whether_filtered_or_not(): void
    {
        $category = new Facet('product_cat', 'Category', name: 'category');
        $pinned = new FakeListing([$this->brand, $category], unmeasured: ['product_cat']);
        $catalog = [self::RESULTS => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]]];
        $filtered = [...$catalog, self::UNFILTERED => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]]];

        $bare = $this->countPlacing($this->listingOf($pinned, new ListingState, $catalog), $this->brand, $category);
        $ticked = $this->countPlacing($this->listingOf($pinned, new ListingState(facets: ['product_brand' => ['acme']]), $filtered), $this->brand, $category);

        $this->assertSame([1, 1], [$bare, $ticked]);
    }

    /** A listing with no term facet counts nothing on the whole catalogue: the price still holds its place once searched. */
    #[Test]
    public function it_keeps_the_price_in_the_row_of_a_listing_without_term_facets_once_searched(): void
    {
        $priceOnly = new FakeListing([$this->price]);
        $searched = $this->listingOf($priceOnly, new ListingState(query: 'cream'), [
            self::RESULTS => ['facetDistribution' => [], 'facetStats' => $this->priceRange(20.0, 20.0)],
        ]);

        $this->assertFalse($searched->isShown($this->price));
        $this->assertSame(1, $this->countPlacing($searched, $this->price));
    }

    private function countPlacing(ResolvedListing $listing, Facet|PriceFilter ...$filters): int
    {
        $placement = $listing->placement();
        $mark = $placement->placedFilterCount();

        foreach ($filters as $filter) {
            $placement->placeFilter($filter, $filter::class);
        }

        return $placement->catalogFilterCountSince($mark);
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
        return $this->listingOf(new FakeListing([$this->brand, $this->size, $this->price]), $state, $responses);
    }

    /**
     * @param  array<string, array<string, mixed>>  $responses
     */
    private function listingOf(FakeListing $fake, ListingState $state, array $responses): ResolvedListing
    {
        return new ResolvedListing(
            $fake,
            $state,
            new ListingSearch(new FakeSearchEngine($responses), new DisjunctiveFacetCounter),
            new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),

            new EngineLimits(1000),
        );
    }
}
