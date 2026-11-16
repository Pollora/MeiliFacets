<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\MeilisearchQuery;

use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\SearchScope;
use Modules\MeiliFacets\Search\FacetQuery;
use Modules\MeiliFacets\Search\MeilisearchQuery;
use Modules\MeiliFacets\Search\PriceQuery;
use Modules\MeiliFacets\Search\QueryPlan;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchScopedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeVariantScopedListing;

const SEARCHED_FIELDS = ['post_title', 'metas._sku'];

/**
 * @param  array<string, mixed>  $query
 * @return array<string, mixed>
 */
function translated(array $query): array
{
    return new MeilisearchQuery('posts')->from($query)->toArray();
}

function scopedListing(string $baseQuery): FakeSearchScopedListing
{
    $listing = new FakeListing(
        [new Facet('product_brand', 'Brand'), new PriceFilter('Price')],
        ['post_type = "product"'],
        baseQuery: $baseQuery,
    );

    return new FakeSearchScopedListing($listing, new SearchScope(['post_type = "product"'], SEARCHED_FIELDS));
}

it('sends every search that carries the term on the scope fields', function (): void {
    $listing = scopedListing('');
    $state = new ListingState(query: 'ser');
    $queries = [
        'results' => QueryPlan::results($listing, $state, []),
        'counts' => QueryPlan::measureWithout($listing, $state, new FacetQuery(new Facet('product_brand', 'Brand'))),
        'price bounds' => QueryPlan::measureWithout($listing, $state, new PriceQuery(new PriceFilter('Price'))),
    ];

    foreach ($queries as $name => $query) {
        $this->assertSame(SEARCHED_FIELDS, translated($query)['attributesToSearchOn'] ?? null, $name);
    }
});

it('sends the unfiltered count of a routed search on the scope fields', function (): void {
    $query = QueryPlan::unfiltered(scopedListing('creme'));

    expect(translated($query)['attributesToSearchOn'] ?? null)->toBe(SEARCHED_FIELDS);
});

/** Without it, a product matching through two variants is listed twice. */
it('sends the field hits are made distinct by', function (): void {
    $state = new ListingState([FakeVariantScopedListing::SIZE => ['15ml', '400ml']]);
    $query = QueryPlan::results(new FakeVariantScopedListing, $state, []);

    expect(translated($query)['distinct'] ?? null)->toBe('parent_id');
});

it('searches every field when nothing is searched', function (): void {
    $query = QueryPlan::results(scopedListing(''), new ListingState, []);

    $this->assertArrayNotHasKey('attributesToSearchOn', translated($query));
});
