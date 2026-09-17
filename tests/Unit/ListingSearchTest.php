<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ListingSearchTest extends TestCase
{
    private const string RESULTS = 'results';

    private const string COUNT = 'count:';

    private const string BOUNDS = 'bounds';

    private const string UNFILTERED = 'unfiltered';

    #[Test]
    public function it_sends_every_search_in_a_single_request(): void
    {
        $engine = new FakeSearchEngine;
        $state = new ListingState(['product_brand' => ['acme']]);

        $this->searchWith($engine)->run(FakeListing::withBrandAndCategory(), $state);

        $this->assertSame(1, $engine->calls);
        $this->assertSame([self::RESULTS, self::COUNT.'product_brand', self::UNFILTERED], array_keys($engine->received[0]));
    }

    #[Test]
    public function it_counts_the_unfiltered_listing_only_once_a_visitor_narrows_it(): void
    {
        $engine = new FakeSearchEngine;
        $listing = FakeListing::withBrandAndCategory();

        $this->searchWith($engine)->run($listing, new ListingState(sort: 'price_asc', page: 3));
        $this->searchWith($engine)->run($listing, new ListingState(query: 'coat'));

        $this->assertSame([self::RESULTS], array_keys($engine->received[0]));
        $this->assertSame([self::RESULTS, self::UNFILTERED], array_keys($engine->received[1]));
    }

    #[Test]
    public function it_reads_what_the_unfiltered_listing_offers_apart_from_the_counts(): void
    {
        $engine = new FakeSearchEngine([
            self::RESULTS => ['facetDistribution' => ['facets.product_cat' => ['coats' => 2]]],
            self::UNFILTERED => ['facetDistribution' => ['facets.product_cat' => ['coats' => 4, 'hats' => 3]]],
        ]);

        $results = $this->searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState(query: 'coat'));

        $this->assertSame(['coats' => 2], $results->distribution('product_cat'));
        $this->assertSame(['coats' => 4, 'hats' => 3], $results->unfilteredDistribution('product_cat'));
    }

    #[Test]
    public function it_has_no_unfiltered_distribution_for_a_listing_nobody_narrowed(): void
    {
        $engine = new FakeSearchEngine([
            self::RESULTS => ['facetDistribution' => ['facets.product_cat' => ['coats' => 2]]],
        ]);

        $results = $this->searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState);

        $this->assertNull($results->unfilteredDistribution('product_cat'));
    }

    #[Test]
    public function it_reads_the_cards_of_the_main_response(): void
    {
        $engine = new FakeSearchEngine([self::RESULTS => [
            'hits' => [['card' => ['title' => 'Coat']], ['card' => ['title' => 'Scarf']], ['no_card' => true]],
            'totalHits' => 27,
        ]]);

        $results = $this->searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState);

        $this->assertSame(27, $results->total);
        $this->assertSame([['title' => 'Coat'], ['title' => 'Scarf']], $results->cards());
    }

    #[Test]
    public function it_reads_a_constrained_facet_from_its_own_response(): void
    {
        $state = new ListingState(['product_brand' => ['acme']]);
        $engine = new FakeSearchEngine([
            self::RESULTS => ['facetDistribution' => ['facets.product_cat' => ['coats' => 2]]],
            self::COUNT.'product_brand' => [
                'facetDistribution' => ['facets.product_brand' => ['acme' => 2, 'globex' => 5]],
            ],
        ]);

        $results = $this->searchWith($engine)->run(FakeListing::withBrandAndCategory(), $state);

        $this->assertSame(['acme' => 2, 'globex' => 5], $results->distribution('product_brand'));
        $this->assertSame(['coats' => 2], $results->distribution('product_cat'));
    }

    #[Test]
    public function it_reads_the_bounds_from_the_search_that_lifted_the_price(): void
    {
        $engine = new FakeSearchEngine([
            self::RESULTS => ['facetStats' => ['price.min' => ['min' => 28.0], 'price.max' => ['max' => 109.0]]],
            self::BOUNDS => ['facetStats' => ['price.min' => ['min' => 0.0], 'price.max' => ['max' => 199.0]]],
        ]);
        $state = new ListingState(price: new Range(55.0, 120.0));

        $results = $this->searchWith($engine)->run(FakeListing::withPriceAndBrand(), $state);

        $this->assertSame(['price.min' => ['min' => 0.0], 'price.max' => ['max' => 199.0]], $results->facetStats);
    }

    #[Test]
    public function it_reads_the_bounds_off_the_main_response_while_no_range_is_held(): void
    {
        $engine = new FakeSearchEngine([
            self::RESULTS => ['facetStats' => ['price.min' => ['min' => 0.0]]],
        ]);

        $results = $this->searchWith($engine)->run(FakeListing::withPriceAndBrand(), new ListingState);

        $this->assertSame([self::RESULTS], array_keys($engine->received[0]));
        $this->assertSame(['price.min' => ['min' => 0.0]], $results->facetStats);
    }

    #[Test]
    public function it_survives_an_engine_answering_nothing(): void
    {
        $results = $this->searchWith(new FakeSearchEngine)->run(FakeListing::withBrandAndCategory(), new ListingState);

        $this->assertSame(0, $results->total);
        $this->assertSame([], $results->cards());
        $this->assertSame([], $results->distribution('product_brand'));
    }

    private function searchWith(FakeSearchEngine $engine): ListingSearch
    {
        return new ListingSearch($engine, new DisjunctiveFacetCounter);
    }
}
