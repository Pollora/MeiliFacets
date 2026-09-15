<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
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
    public function it_carries_the_base_filter_of_an_untouched_listing(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState);

        $this->assertSame('post_type = "product"', $query['filter']);
        $this->assertSame(16, $query['hitsPerPage']);
        $this->assertSame(1, $query['page']);
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

        $bounds = QueryPlan::priceBounds($listing, $state);

        $this->assertStringNotContainsString('price.', $bounds['filter']);
        $this->assertStringContainsString('post_type = "product"', $bounds['filter']);
        $this->assertStringContainsString('facets.product_brand = "acme"', $bounds['filter']);
        $this->assertSame(['price.min', 'price.max'], $bounds['facets']);
        $this->assertSame(0, $bounds['hitsPerPage']);
    }

    #[Test]
    public function it_measures_the_price_apart_only_once_a_range_is_held(): void
    {
        $priced = FakeListing::withPriceAndBrand();

        $this->assertFalse(QueryPlan::measuresPriceApart($priced, new ListingState));
        $this->assertTrue(QueryPlan::measuresPriceApart($priced, new ListingState(price: new Range(max: 120.0))));
        $this->assertFalse(QueryPlan::measuresPriceApart($this->listing, new ListingState(price: new Range(max: 120.0))));
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
}
