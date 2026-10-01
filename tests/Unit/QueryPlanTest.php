<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use LogicException;
use Modules\MeiliFacets\Enums\QueryParameter;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Listing\Sort;
use Modules\MeiliFacets\Listing\SortFilter;
use Modules\MeiliFacets\Listing\StateReader;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\FacetQuery;
use Modules\MeiliFacets\Search\FilterQuery;
use Modules\MeiliFacets\Search\PriceQuery;
use Modules\MeiliFacets\Search\QueryPlan;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchScopedListing;
use PHPUnit\Framework\Attributes\DataProvider;
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

    /** No field of the listing can take a term off a search WordPress routed: a typed one is ignored. */
    #[Test]
    public function it_searches_what_the_page_asks_whatever_the_state_holds(): void
    {
        $listing = new FakeListing(baseQuery: 'creme');

        $this->assertSame('creme', QueryPlan::results($listing, new ListingState, [])['q']);
        $this->assertSame('creme', QueryPlan::unfiltered($listing)['q']);
        $this->assertSame('creme', QueryPlan::results($listing, new ListingState(query: 'lait'), [])['q']);
    }

    #[Test]
    public function it_carries_the_base_filter_of_an_untouched_listing(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState, []);

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

        foreach ([QueryPlan::results($listing, $state, []), QueryPlan::measureWithout($listing, $state, $filters[0]), QueryPlan::measureWithout($listing, $state, $filters[1])] as $query) {
            $this->assertStringContainsString('price.onsale = "true"', $query['filter']);
        }

        $this->assertSame([], QueryPlan::results($listing, $state, [])['sort']);
    }

    #[Test]
    public function it_asks_the_main_search_for_the_field_a_sort_filters_on(): void
    {
        $this->assertContains('price.onsale', QueryPlan::results(FakeListing::withPromotions(), new ListingState, [])['facets']);
        $this->assertNotContains('price.onsale', QueryPlan::results($this->listing, new ListingState, [])['facets']);
    }

    #[Test]
    public function it_asks_once_for_a_field_several_sorts_filter_on(): void
    {
        $listing = new FakeListing([], [], sorts: [
            'on_sale' => Sort::filtering('On sale', SortFilter::whereTrue('price.onsale')),
            'on_sale_too' => Sort::filtering('On sale again', SortFilter::whereTrue('price.onsale')),
        ]);

        $this->assertSame(['price.onsale'], QueryPlan::results($listing, new ListingState, [])['facets']);
    }

    #[Test]
    public function it_filters_nothing_for_a_sort_that_only_orders(): void
    {
        $query = QueryPlan::results(FakeListing::withPromotions(), new ListingState(sort: 'price_asc'), []);

        $this->assertStringNotContainsString('price.onsale', $query['filter']);
    }

    #[Test]
    public function it_asks_only_for_the_card_and_its_document_id(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState, []);

        $this->assertSame(['ID', 'card'], $query['attributesToRetrieve']);
    }

    /**
     * `hitsPerPage`/`page` answer with an exhaustive `totalHits`, where
     * `limit`/`offset` only estimate it and stop at `maxTotalHits`.
     */
    #[Test]
    public function it_asks_for_a_page_rather_than_an_offset(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState(page: 3), []);

        $this->assertSame(3, $query['page']);
        $this->assertArrayNotHasKey('offset', $query);
    }

    #[Test]
    public function it_sorts_only_by_a_sort_the_listing_declares(): void
    {
        $known = QueryPlan::results($this->listing, new ListingState(sort: 'price_asc'), []);
        $unknown = QueryPlan::results($this->listing, new ListingState(sort: 'made_up'), []);

        $this->assertSame(['metas._price:asc'], $known['sort']);
        $this->assertArrayNotHasKey('sort', $unknown);
    }

    #[Test]
    public function it_counts_every_facet_on_the_main_response_while_none_constrains(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState, []);

        $this->assertSame(['facets.product_brand', 'facets.product_cat'], $query['facets']);
        $this->assertSame([], (new DisjunctiveFacetCounter)->queries($this->listing, new ListingState));
    }

    /**
     * A constrained multi-selection facet moves to a search of its own, so its
     * other values keep a count and stay reachable.
     */
    #[Test]
    public function it_leaves_off_the_main_search_the_fields_it_is_told_are_measured_separately(): void
    {
        $state = new ListingState(['product_brand' => ['acme']]);

        $whole = QueryPlan::results($this->listing, $state, [])['facets'];
        $without = QueryPlan::results($this->listing, $state, [FacetQuery::keyFor('product_brand')])['facets'];

        $this->assertSame(['facets.product_brand', 'facets.product_cat'], $whole);
        $this->assertSame(['facets.product_cat'], $without);
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

    /**
     * A range drawn from the set its own bounds filtered would narrow at every
     * move, with no way back to the prices it just hid.
     */
    #[Test]
    public function it_lifts_the_price_from_the_search_that_measures_its_bounds(): void
    {
        $listing = FakeListing::withPriceAndBrand();
        $state = new ListingState(['product_brand' => ['acme']], price: new Range(55.0, 120.0));

        $bounds = QueryPlan::measureWithout($listing, $state, $this->priceOf($listing));

        $this->assertStringNotContainsString('price.', $bounds['filter']);
        $this->assertStringContainsString('post_type = "product"', $bounds['filter']);
        $this->assertStringContainsString('facets.product_brand = "acme"', $bounds['filter']);
        $this->assertSame(['price.min', 'price.max'], $bounds['facets']);
        $this->assertSame(0, $bounds['hitsPerPage']);
    }

    #[Test]
    public function it_keeps_the_held_range_on_the_search_that_counts_a_facet_separately(): void
    {
        $listing = FakeListing::withPriceAndBrand();
        $state = new ListingState(['product_brand' => ['acme']], price: new Range(max: 120.0));
        $brand = QueryPlan::filterQueries($listing)[0];

        $counting = QueryPlan::measureWithout($listing, $state, $brand);

        $this->assertStringContainsString('price.min <= 120', $counting['filter']);
        $this->assertStringNotContainsString('facets.product_brand', $counting['filter']);
    }

    #[Test]
    public function it_plans_no_price_for_a_listing_that_declares_none(): void
    {
        $prices = array_filter(QueryPlan::filterQueries($this->listing), static fn (FilterQuery $filter): bool => $filter instanceof PriceQuery);

        $this->assertSame([], $prices);
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
        $this->assertContains('facets.product_cat', QueryPlan::results($this->listing, $state, [])['facets']);
    }

    /**
     * The client reads the same cases (`listing-query.test.ts`): a first render and the first gesture
     * after it must search the same products on the same fields.
     *
     * @param  array{query: string, baseQuery: string, q: string, filter: string, attributesToSearchOn: list<string>|null}  $case
     */
    #[DataProvider('searchScopeCases')]
    #[Test]
    public function it_picks_the_search_scope_by_the_rule_the_client_follows(array $case): void
    {
        $listing = $this->scopedListing($case['baseQuery']);
        $parameters = new UrlParameters([]);
        $state = new StateReader($parameters)->read($listing, [$parameters->reserved(QueryParameter::Query) => $case['query']]);
        $results = QueryPlan::results($listing, $state, []);
        $counting = QueryPlan::measureWithout($listing, $state, new FacetQuery(new Facet('product_brand', 'Brand')));

        foreach ([$results, $counting] as $query) {
            $this->assertSame($case['q'], $query['q']);
            $this->assertSame($case['filter'], $query['filter']);
            $this->assertSame($case['attributesToSearchOn'], $query['attributesToSearchOn'] ?? null);
        }
    }

    /** The values a facet offers come from the products the routed search can find, never from the catalogue. */
    #[Test]
    public function it_counts_the_unfiltered_listing_of_a_routed_search_in_the_search_scope(): void
    {
        $routed = QueryPlan::unfiltered($this->scopedListing('creme'));
        $browsed = QueryPlan::unfiltered($this->scopedListing(''));

        $routedCase = $this->sharedScopeCase('a term WordPress routed reads what the search reads');
        $browsedCase = $this->sharedScopeCase('nothing searched browses the catalogue on every field');

        $this->assertSame($routedCase['filter'], $routed['filter']);
        $this->assertSame($routedCase['attributesToSearchOn'], $routed['attributesToSearchOn']);
        $this->assertSame($browsedCase['filter'], $browsed['filter']);
        $this->assertArrayNotHasKey('attributesToSearchOn', $browsed);
    }

    /** A listing that declares no scope of its own searches its base filter, on every field. */
    #[Test]
    public function it_searches_the_base_filter_of_a_listing_without_a_scope(): void
    {
        $query = QueryPlan::results($this->listing, new ListingState(query: 'coat'), []);

        $this->assertSame('post_type = "product"', $query['filter']);
        $this->assertArrayNotHasKey('attributesToSearchOn', $query);
    }

    /**
     * @return iterable<string, array{array{query: string, baseQuery: string, q: string, filter: string, attributesToSearchOn: list<string>|null}}>
     */
    public static function searchScopeCases(): iterable
    {
        foreach (self::sharedScopeCases()['cases'] as $case) {
            yield $case['case'] => [$case];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedScopeCase(string $name): array
    {
        $cases = array_column(self::sharedScopeCases()['cases'], null, 'case');

        if (! isset($cases[$name])) {
            throw new LogicException("No shared scope case is named \"{$name}\".");
        }

        return $cases[$name];
    }

    /**
     * @return array{listing: array{baseFilter: list<string>, searchScope: array{filter: list<string>, fields: list<string>}}, cases: list<array<string, mixed>>}
     */
    private static function sharedScopeCases(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../search-scope-cases.json'), true, flags: JSON_THROW_ON_ERROR);
    }

    private function scopedListing(string $baseQuery): FakeSearchScopedListing
    {
        $shared = self::sharedScopeCases()['listing'];
        $listing = new FakeListing([new Facet('product_brand', 'Brand')], $shared['baseFilter'], baseQuery: $baseQuery);

        return new FakeSearchScopedListing($listing, new SearchScope($shared['searchScope']['filter'], $shared['searchScope']['fields']));
    }

    private function priceOf(FakeListing $listing): PriceQuery
    {
        $price = PriceFilter::among($listing->filters());

        $this->assertInstanceOf(PriceFilter::class, $price);

        return new PriceQuery($price);
    }
}
