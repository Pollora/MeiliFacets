<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\FilterQuery;
use Modules\MeiliFacets\Search\PriceQuery;
use Modules\MeiliFacets\Search\QueryPlan;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueryPlanTest extends TestCase
{
    private FakeListing $listing;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listing = FakeListing::withBrandAndCategory();
    }

    #[Test]
    public function it_searches_what_the_page_asks_unless_the_visitor_asked_otherwise(): void
    {
        $listing = new FakeListing(baseQuery: 'creme');

        $this->assertSame('creme', QueryPlan::results($listing, new ListingState)['q']);
        $this->assertSame('creme', QueryPlan::unfiltered($listing)['q']);
        $this->assertSame('lait', QueryPlan::results($listing, new ListingState(query: 'lait'))['q']);
    }

    #[Test]
    public function it_carries_the_base_filter_of_an_untouched_listing(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState);

        $this->assertSame('post_type = "product"', $query['filter']);
        $this->assertSame(16, $query['hitsPerPage']);
        $this->assertSame(1, $query['page']);
    }

    /** A value that survives only in the base filter's slugs would reach the page, hidden but readable in the source. */
    #[Test]
    public function it_counts_the_unfiltered_listing_under_its_base_filter_alone(): void
    {
        $query = QueryPlan::unfiltered($this->listing);

        $this->assertSame('post_type = "product"', $query['filter']);
        $this->assertSame('', $query['q']);
        $this->assertSame(['facets.product_brand', 'facets.product_cat'], $query['facets']);
        $this->assertSame(0, $query['hitsPerPage']);
    }

    #[Test]
    public function it_filters_every_search_by_a_sort_that_filters(): void
    {
        $listing = FakeListing::withPromotions();
        $state = new ListingState(['product_brand' => ['acme']], 'on_sale', price: new Range(min: 20.0));
        $filters = QueryPlan::filterQueries($listing);

        foreach ([QueryPlan::results($listing, $state), QueryPlan::apart($listing, $state, $filters[0]), QueryPlan::apart($listing, $state, $filters[1])] as $query) {
            $this->assertStringContainsString('price.onsale = "true"', $query['filter']);
        }

        $this->assertSame([], QueryPlan::results($listing, $state)['sort']);
    }

    #[Test]
    public function it_asks_the_main_search_for_the_field_a_sort_filters_on(): void
    {
        $this->assertContains('price.onsale', QueryPlan::results(FakeListing::withPromotions(), new ListingState)['facets']);
        $this->assertNotContains('price.onsale', QueryPlan::results($this->listing, new ListingState)['facets']);
    }

    #[Test]
    public function it_asks_once_for_a_field_several_sorts_filter_on(): void
    {
        $listing = new FakeListing([], [], sorts: [
            'on_sale' => Sort::filtering('On sale', SortFilter::whereTrue('price.onsale')),
            'on_sale_too' => Sort::filtering('On sale again', SortFilter::whereTrue('price.onsale')),
        ]);

        $this->assertSame(['price.onsale'], QueryPlan::results($listing, new ListingState)['facets']);
    }

    #[Test]
    public function it_filters_nothing_for_a_sort_that_only_orders(): void
    {
        $query = QueryPlan::results(FakeListing::withPromotions(), new ListingState(sort: 'price_asc'));

        $this->assertStringNotContainsString('price.onsale', $query['filter']);
    }

    #[Test]
    public function it_asks_only_for_the_card(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState);

        $this->assertSame(['card'], $query['attributesToRetrieve']);
    }

    /**
     * `hitsPerPage`/`page` answer with an exhaustive `totalHits`, where
     * `limit`/`offset` only estimate it and stop at `maxTotalHits`.
     */
    #[Test]
    public function it_asks_for_a_page_rather_than_an_offset(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState(page: 3));

        $this->assertSame(3, $query['page']);
        $this->assertArrayNotHasKey('offset', $query);
    }

    #[Test]
    public function it_sorts_only_by_a_sort_the_listing_declares(): void
    {
        $known = QueryPlan::results($this->listing, new ListingState(sort: 'price_asc'));
        $unknown = QueryPlan::results($this->listing, new ListingState(sort: 'made_up'));

        $this->assertSame(['metas._price:asc'], $known['sort']);
        $this->assertArrayNotHasKey('sort', $unknown);
    }

    #[Test]
    public function it_counts_every_facet_on_the_main_response_while_none_constrains(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState);

        $this->assertSame(['facets.product_brand', 'facets.product_cat'], $query['facets']);
        $this->assertSame([], (new DisjunctiveFacetCounter)->queries($this->listing, new ListingState));
    }

    /**
     * A constrained multi-selection facet moves to a search of its own, so its
     * other values keep a count and stay reachable.
     */
    #[Test]
    public function it_moves_a_constrained_multi_facet_to_its_own_search(): void
    {
        $state = new ListingState(['product_brand' => ['acme']]);

        $main = QueryPlan::results($this->listing, $state);
        $extra = (new DisjunctiveFacetCounter)->queries($this->listing, $state);

        $this->assertSame(['facets.product_cat'], $main['facets']);
        $this->assertSame(['product_brand'], array_keys($extra));
        $this->assertSame(['facets.product_brand'], $extra['product_brand']['facets']);
    }

    #[Test]
    public function it_lifts_only_the_counted_facet_from_its_own_filter(): void
    {
        $state = new ListingState(['product_brand' => ['acme'], 'product_cat' => ['coats']]);

        $counting = (new DisjunctiveFacetCounter)->queries($this->listing, $state)['product_brand'];

        $this->assertStringNotContainsString('product_brand', $counting['filter']);
        $this->assertStringContainsString('facets.product_cat = "coats"', $counting['filter']);
        $this->assertSame(0, $counting['hitsPerPage']);
    }

    #[Test]
    public function it_never_counts_a_facet_twice(): void
    {
        $state = new ListingState(['product_brand' => ['acme']]);

        $onMain = QueryPlan::results($this->listing, $state)['facets'];
        $apart = (new DisjunctiveFacetCounter)->queries($this->listing, $state);

        foreach ($apart as $taxonomy => $query) {
            $this->assertNotContains('facets.'.$taxonomy, $onMain);
        }
    }

    /**
     * A range drawn from the set its own bounds filtered would narrow at every
     * move, with no way back to the prices it just hid.
     */
    #[Test]
    public function it_lifts_the_price_from_the_search_that_measures_its_bounds(): void
    {
        $listing = FakeListing::withPriceAndBrand();
        $state = new ListingState(['product_brand' => ['acme']], price: new Range(55.0, 120.0));

        $bounds = QueryPlan::apart($listing, $state, $this->priceOf($listing));

        $this->assertStringNotContainsString('price.', $bounds['filter']);
        $this->assertStringContainsString('post_type = "product"', $bounds['filter']);
        $this->assertStringContainsString('facets.product_brand = "acme"', $bounds['filter']);
        $this->assertSame(['price.min', 'price.max'], $bounds['facets']);
        $this->assertSame(0, $bounds['hitsPerPage']);
    }

    #[Test]
    public function it_keeps_the_held_range_on_the_search_that_counts_a_facet_apart(): void
    {
        $listing = FakeListing::withPriceAndBrand();
        $state = new ListingState(['product_brand' => ['acme']], price: new Range(max: 120.0));
        $brand = QueryPlan::filterQueries($listing)[0];

        $counting = QueryPlan::apart($listing, $state, $brand);

        $this->assertStringContainsString('price.min <= 120', $counting['filter']);
        $this->assertStringNotContainsString('facets.product_brand', $counting['filter']);
    }

    #[Test]
    public function it_plans_no_price_for_a_listing_that_declares_none(): void
    {
        $prices = array_filter(QueryPlan::filterQueries($this->listing), static fn (FilterQuery $filter): bool => $filter instanceof PriceQuery);

        $this->assertSame([], $prices);
    }

    #[Test]
    public function it_never_asks_the_main_search_for_bounds_it_measures_apart(): void
    {
        $listing = FakeListing::withPriceAndBrand();

        $open = QueryPlan::results($listing, new ListingState)['facets'];
        $held = QueryPlan::results($listing, new ListingState(price: new Range(55.0)))['facets'];

        $this->assertSame(['facets.product_brand', 'price.min', 'price.max'], $open);
        $this->assertSame(['facets.product_brand'], $held);
    }

    /**
     * A single-selection facet reads correctly off the main response, so it must
     * never cost an extra search.
     */
    #[Test]
    public function it_never_gives_a_single_selection_facet_its_own_search(): void
    {
        $state = new ListingState(['product_cat' => ['coats']]);

        $this->assertSame([], (new DisjunctiveFacetCounter)->queries($this->listing, $state));
        $this->assertContains('facets.product_cat', QueryPlan::results($this->listing, $state)['facets']);
    }

    private function priceOf(FakeListing $listing): PriceQuery
    {
        $price = PriceFilter::among($listing->filters());

        $this->assertInstanceOf(PriceFilter::class, $price);

        return new PriceQuery($price);
    }
}
