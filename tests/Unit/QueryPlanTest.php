<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\QueryPlan;

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
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultSortedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchScopedListing;

beforeEach(function (): void {
    $this->listing = FakeListing::withBrandAndCategory();
});

/**
 * @return array<string, mixed>
 */
function sharedScopeCase(string $name): array
{
    $cases = array_column(sharedScopeCases()['cases'], null, 'case');

    if (! isset($cases[$name])) {
        throw new LogicException("No shared scope case is named \"{$name}\".");
    }

    return $cases[$name];
}

/**
 * @return array{listing: array{baseFilter: list<string>, searchScope: array{filter: list<string>, fields: list<string>}}, cases: list<array<string, mixed>>}
 */
function sharedScopeCases(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/../search-scope-cases.json'), true, flags: JSON_THROW_ON_ERROR);
}

function scopedListing(string $baseQuery): FakeSearchScopedListing
{
    $shared = sharedScopeCases()['listing'];
    $listing = new FakeListing([new Facet('product_brand', 'Brand')], $shared['baseFilter'], baseQuery: $baseQuery);

    return new FakeSearchScopedListing($listing, new SearchScope($shared['searchScope']['filter'], $shared['searchScope']['fields']));
}

function newestFirst(string $baseQuery = ''): FakeDefaultSortedListing
{
    $sorts = [
        'price_asc' => new Sort('Price', ['metas._price:asc']),
        'on_sale' => Sort::filtering('On sale', SortFilter::whereTrue('price.onsale')),
    ];

    return new FakeDefaultSortedListing(new FakeListing(baseQuery: $baseQuery, sorts: $sorts), ['post_date:desc']);
}

function priceOf(FakeListing $listing): PriceQuery
{
    $price = PriceFilter::among($listing->filters());

    expect($price)->toBeInstanceOf(PriceFilter::class);

    return new PriceQuery($price);
}

/** No field of the listing can take a term off a search WordPress routed: a typed one is ignored. */
it('searches what the page asks whatever the state holds', function (): void {
    $listing = new FakeListing(baseQuery: 'creme');

    expect(QueryPlan::results($listing, new ListingState, [])['q'])->toBe('creme');
    expect(QueryPlan::unfiltered($listing)['q'])->toBe('creme');
    expect(QueryPlan::results($listing, new ListingState(query: 'lait'), [])['q'])->toBe('creme');
});

it('carries the base filter of an untouched listing', function (): void {
    $query = QueryPlan::results($this->listing, new ListingState, []);

    expect($query['filter'])->toBe('post_type = "product"');
    expect($query['hitsPerPage'])->toBe(16);
    expect($query['page'])->toBe(1);
});

/** A value that survives only in the base filter's slugs would reach the page, hidden but readable in the source. */
it('counts the unfiltered listing under its base filter alone', function (): void {
    $query = QueryPlan::unfiltered($this->listing);

    expect($query['filter'])->toBe('post_type = "product"');
    expect($query['q'])->toBe('');
    expect($query['facets'])->toBe(['facets.product_brand', 'facets.product_cat']);
    expect($query['hitsPerPage'])->toBe(0);
});

it('filters every search by a sort that filters', function (): void {
    $listing = FakeListing::withPromotions();
    $state = new ListingState(['product_brand' => ['acme']], 'on_sale', price: new Range(min: 20.0));
    $filters = QueryPlan::filterQueries($listing);

    foreach ([QueryPlan::results($listing, $state, []), QueryPlan::measureWithout($listing, $state, $filters[0]), QueryPlan::measureWithout($listing, $state, $filters[1])] as $query) {
        expect($query['filter'])->toContain('price.onsale = "true"');
    }

    $this->assertArrayNotHasKey('sort', QueryPlan::results($listing, $state, []));
});

it('asks the main search for the field a sort filters on', function (): void {
    expect(QueryPlan::results(FakeListing::withPromotions(), new ListingState, [])['facets'])->toContain('price.onsale');
    expect(QueryPlan::results($this->listing, new ListingState, [])['facets'])->not->toContain('price.onsale');
});

it('asks once for a field several sorts filter on', function (): void {
    $listing = new FakeListing([], [], sorts: [
        'on_sale' => Sort::filtering('On sale', SortFilter::whereTrue('price.onsale')),
        'on_sale_too' => Sort::filtering('On sale again', SortFilter::whereTrue('price.onsale')),
    ]);

    expect(QueryPlan::results($listing, new ListingState, [])['facets'])->toBe(['price.onsale']);
});

it('filters nothing for a sort that only orders', function (): void {
    $query = QueryPlan::results(FakeListing::withPromotions(), new ListingState(sort: 'price_asc'), []);

    expect($query['filter'])->not->toContain('price.onsale');
});

it('asks only for the card and its document id', function (): void {
    $query = QueryPlan::results($this->listing, new ListingState, []);

    expect($query['attributesToRetrieve'])->toBe(['ID', 'card']);
});

/** `hitsPerPage`/`page` answer with an exhaustive `totalHits`, where `limit`/`offset` only estimate it and stop at `maxTotalHits`. */
it('asks for a page rather than an offset', function (): void {
    $query = QueryPlan::results($this->listing, new ListingState(page: 3), []);

    expect($query['page'])->toBe(3);
    $this->assertArrayNotHasKey('offset', $query);
});

it('sorts only by a sort the listing declares', function (): void {
    $known = QueryPlan::results($this->listing, new ListingState(sort: 'price_asc'), []);
    $unknown = QueryPlan::results($this->listing, new ListingState(sort: 'made_up'), []);

    expect($known['sort'])->toBe(['metas._price:asc']);
    $this->assertArrayNotHasKey('sort', $unknown);
});

it('follows the listing order while no sort is chosen', function (): void {
    $query = QueryPlan::results(newestFirst(), new ListingState, []);

    expect($query['sort'])->toBe(['post_date:desc']);
});

test('a chosen sort replaces the listing order', function (): void {
    $query = QueryPlan::results(newestFirst(), new ListingState(sort: 'price_asc'), []);

    expect($query['sort'])->toBe(['metas._price:asc']);
});

test('a sort that only filters keeps the listing order', function (): void {
    $query = QueryPlan::results(newestFirst(), new ListingState(sort: 'on_sale'), []);

    expect($query['sort'])->toBe(['post_date:desc']);
});

test('a searched term keeps the engine order', function (): void {
    $typed = QueryPlan::results(newestFirst(), new ListingState(query: 'creme'), []);
    $routed = QueryPlan::results(newestFirst('creme'), new ListingState, []);

    $this->assertArrayNotHasKey('sort', $typed);
    $this->assertArrayNotHasKey('sort', $routed);
});

it('sends the listing order as a list whatever its keys', function (): void {
    $listing = new FakeDefaultSortedListing($this->listing, [1 => 'post_date:desc']);

    expect(QueryPlan::results($listing, new ListingState, [])['sort'])->toBe(['post_date:desc']);
});

test('an empty listing order keeps the engine order', function (): void {
    $listing = new FakeDefaultSortedListing($this->listing, []);

    $this->assertArrayNotHasKey('sort', QueryPlan::results($listing, new ListingState, []));
});

it('counts every facet on the main response while none constrains', function (): void {
    $query = QueryPlan::results($this->listing, new ListingState, []);

    expect($query['facets'])->toBe(['facets.product_brand', 'facets.product_cat']);
    expect((new DisjunctiveFacetCounter)->queries($this->listing, new ListingState))->toBe([]);
});

/** A constrained multi-selection facet moves to a search of its own, so its other values keep a count and stay reachable. */
it('leaves off the main search the fields it is told are measured separately', function (): void {
    $state = new ListingState(['product_brand' => ['acme']]);

    $whole = QueryPlan::results($this->listing, $state, [])['facets'];
    $without = QueryPlan::results($this->listing, $state, [FacetQuery::keyFor('product_brand')])['facets'];

    expect($whole)->toBe(['facets.product_brand', 'facets.product_cat']);
    expect($without)->toBe(['facets.product_cat']);
});

it('lifts only the counted facet from its own filter', function (): void {
    $state = new ListingState(['product_brand' => ['acme'], 'product_cat' => ['coats']]);

    $counting = (new DisjunctiveFacetCounter)->queries($this->listing, $state)['product_brand'];

    expect($counting['filter'])->not->toContain('product_brand');
    expect($counting['filter'])->toContain('facets.product_cat = "coats"');
    expect($counting['hitsPerPage'])->toBe(0);
});

/** A range drawn from the set its own bounds filtered would narrow at every move, with no way back to the prices it just hid. */
it('lifts the price from the search that measures its bounds', function (): void {
    $listing = FakeListing::withPriceAndBrand();
    $state = new ListingState(['product_brand' => ['acme']], price: new Range(55.0, 120.0));

    $bounds = QueryPlan::measureWithout($listing, $state, priceOf($listing));

    expect($bounds['filter'])->not->toContain('price.');
    expect($bounds['filter'])->toContain('post_type = "product"');
    expect($bounds['filter'])->toContain('facets.product_brand = "acme"');
    expect($bounds['facets'])->toBe(['price.min', 'price.max']);
    expect($bounds['hitsPerPage'])->toBe(0);
});

it('keeps the held range on the search that counts a facet separately', function (): void {
    $listing = FakeListing::withPriceAndBrand();
    $state = new ListingState(['product_brand' => ['acme']], price: new Range(max: 120.0));
    $brand = QueryPlan::filterQueries($listing)[0];

    $counting = QueryPlan::measureWithout($listing, $state, $brand);

    expect($counting['filter'])->toContain('price.min <= 120');
    expect($counting['filter'])->not->toContain('facets.product_brand');
});

it('plans no price for a listing that declares none', function (): void {
    $prices = array_filter(QueryPlan::filterQueries($this->listing), static fn (FilterQuery $filter): bool => $filter instanceof PriceQuery);

    expect($prices)->toBe([]);
});

/** A single-selection facet reads correctly off the main response, so it must never cost an extra search. */
it('never gives a single selection facet its own search', function (): void {
    $state = new ListingState(['product_cat' => ['coats']]);

    expect((new DisjunctiveFacetCounter)->queries($this->listing, $state))->toBe([]);
    expect(QueryPlan::results($this->listing, $state, [])['facets'])->toContain('facets.product_cat');
});

/** The client reads the same cases (`listing-query.test.ts`): a first render and the first gesture after it must search the same products on the same fields. */
it('picks the search scope by the rule the client follows', function (array $case): void {
    $listing = scopedListing($case['baseQuery']);
    $parameters = new UrlParameters([]);
    $state = new StateReader($parameters)->read($listing, [$parameters->reserved(QueryParameter::Query) => $case['query']]);
    $results = QueryPlan::results($listing, $state, []);
    $counting = QueryPlan::measureWithout($listing, $state, new FacetQuery(new Facet('product_brand', 'Brand')));

    foreach ([$results, $counting] as $query) {
        expect($query['q'])->toBe($case['q']);
        expect($query['filter'])->toBe($case['filter']);
        expect($query['attributesToSearchOn'] ?? null)->toBe($case['attributesToSearchOn']);
    }
})->with(function () {
    foreach (sharedScopeCases()['cases'] as $case) {
        yield $case['case'] => [$case];
    }
});

/** The values a facet offers come from the products the routed search can find, never from the catalogue. */
it('counts the unfiltered listing of a routed search in the search scope', function (): void {
    $routed = QueryPlan::unfiltered(scopedListing('creme'));
    $browsed = QueryPlan::unfiltered(scopedListing(''));

    $routedCase = sharedScopeCase('a term WordPress routed reads what the search reads');
    $browsedCase = sharedScopeCase('nothing searched browses the catalogue on every field');

    expect($routed['filter'])->toBe($routedCase['filter']);
    expect($routed['attributesToSearchOn'])->toBe($routedCase['attributesToSearchOn']);
    expect($browsed['filter'])->toBe($browsedCase['filter']);
    $this->assertArrayNotHasKey('attributesToSearchOn', $browsed);
});

/** A listing that declares no scope of its own searches its base filter, on every field. */
it('searches the base filter of a listing without a scope', function (): void {
    $query = QueryPlan::results($this->listing, new ListingState(query: 'coat'), []);

    expect($query['filter'])->toBe('post_type = "product"');
    $this->assertArrayNotHasKey('attributesToSearchOn', $query);
});
