<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeFacetCounter;
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
    public function it_counts_what_each_filtering_sort_would_keep(): void
    {
        $engine = new FakeSearchEngine([self::RESULTS => ['facetDistribution' => ['price.onsale' => ['true' => 7, 'false' => 40]]]]);

        $results = $this->searchWith($engine)->run(FakeListing::withPromotions(), new ListingState);

        $this->assertSame(['on_sale' => 7], $results->sortMatches);
    }

    #[Test]
    public function it_counts_nothing_kept_when_the_engine_reports_no_match(): void
    {
        $engine = new FakeSearchEngine([self::RESULTS => ['facetDistribution' => ['price.onsale' => ['false' => 47]]]]);

        $results = $this->searchWith($engine)->run(FakeListing::withPromotions(), new ListingState);

        $this->assertSame(['on_sale' => 0], $results->sortMatches);
    }

    #[Test]
    public function it_counts_the_unfiltered_listing_under_a_sort_that_filters(): void
    {
        $engine = new FakeSearchEngine;
        $listing = FakeListing::withPromotions();

        $this->searchWith($engine)->run($listing, new ListingState(sort: 'price_asc'));
        $this->searchWith($engine)->run($listing, new ListingState(sort: 'on_sale'));

        $this->assertNotContains(self::UNFILTERED, array_keys($engine->received[0]));
        $this->assertContains(self::UNFILTERED, array_keys($engine->received[1]));
    }

    #[Test]
    public function it_survives_an_engine_answering_nothing(): void
    {
        $results = $this->searchWith(new FakeSearchEngine)->run(FakeListing::withBrandAndCategory(), new ListingState);

        $this->assertSame(0, $results->total);
        $this->assertSame([], $results->cards());
        $this->assertSame([], $results->distribution('product_brand'));
    }

    #[Test]
    public function it_counts_apart_the_facet_the_counter_measures_apart(): void
    {
        $engine = new FakeSearchEngine;

        $this->searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState(['product_brand' => ['acme']]));

        $this->assertSame(['facets.product_cat'], $engine->received[0][self::RESULTS]['facets']);
        $this->assertSame(['facets.product_brand'], $engine->received[0][self::COUNT.'product_brand']['facets']);
    }

    #[Test]
    public function it_counts_on_the_main_search_what_the_counter_leaves_to_it(): void
    {
        $engine = new FakeSearchEngine;

        new ListingSearch($engine, new FakeFacetCounter)
            ->run(FakeListing::withBrandAndCategory(), new ListingState(['product_brand' => ['acme']]));

        $this->assertSame([self::RESULTS, self::UNFILTERED], array_keys($engine->received[0]));
        $this->assertSame(['facets.product_brand', 'facets.product_cat'], $engine->received[0][self::RESULTS]['facets']);
    }

    #[Test]
    public function it_never_counts_a_facet_twice(): void
    {
        $engine = new FakeSearchEngine;
        $state = new ListingState(['product_brand' => ['acme'], 'product_cat' => ['coats']], price: new Range(55.0));

        $this->searchWith($engine)->run(FakeListing::withPriceAndBrand(), $state);

        $onMain = $engine->received[0][self::RESULTS]['facets'];

        foreach ($engine->received[0] as $key => $query) {
            if ($key !== self::RESULTS && $key !== self::UNFILTERED) {
                $this->assertSame([], array_intersect($query['facets'], $onMain), "\"{$key}\" is counted twice.");
            }
        }
    }

    #[Test]
    public function it_never_asks_the_main_search_for_bounds_it_measures_apart(): void
    {
        $engine = new FakeSearchEngine;
        $listing = FakeListing::withPriceAndBrand();

        $this->searchWith($engine)->run($listing, new ListingState);
        $this->searchWith($engine)->run($listing, new ListingState(price: new Range(55.0)));

        $this->assertSame(['facets.product_brand', 'price.min', 'price.max'], $engine->received[0][self::RESULTS]['facets']);
        $this->assertSame(['facets.product_brand'], $engine->received[1][self::RESULTS]['facets']);
    }

    private function searchWith(FakeSearchEngine $engine): ListingSearch
    {
        return new ListingSearch($engine, new DisjunctiveFacetCounter);
    }
}
