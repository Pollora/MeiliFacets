<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\FacetQuery;
use Modules\MeiliFacets\Search\FilterQuery;
use Modules\MeiliFacets\Search\PriceQuery;
use Modules\MeiliFacets\Search\QueryPlan;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeVariantScopedListing;

// The results read the documents of the variants once a filter concerns a variant.

const VARIANT_RESULTS_OF_VARIATIONS = 'post_type = "product_variation"';

const VARIANT_RESULTS_VARIANT_DOCUMENTS = 'NOT document_kind = "parent"';

function variantResultsQueryKeyed(Listing $listing, string $key): FilterQuery
{
    $queries = array_filter(QueryPlan::filterQueries($listing), static fn (FilterQuery $query): bool => $query->key() === $key);

    return array_values($queries)[0];
}

beforeEach(function (): void {
    $this->listing = new FakeVariantScopedListing;
});

it('reads the products while no filter concerns a variant', function (): void {
    $state = new ListingState(['product_brand' => ['acme']], 'price_asc');

    $query = QueryPlan::results($this->listing, $state, []);

    expect(QueryPlan::readsVariants($this->listing, $state))->toBeFalse();
    expect($query['filter'])->not->toContain(VARIANT_RESULTS_OF_VARIATIONS);
    expect($query)->not->toHaveKey('distinct');
    expect($query['sort'])->toBe(['price.min:asc']);
});

it('reads one variant per product once a size is ticked', function (): void {
    $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']], 'price_asc');

    $query = QueryPlan::results($this->listing, $state, []);

    expect(QueryPlan::readsVariants($this->listing, $state))->toBeTrue();
    expect($query['filter'])->toContain(VARIANT_RESULTS_VARIANT_DOCUMENTS);
    expect($query['filter'])->toContain(VARIANT_RESULTS_OF_VARIATIONS);
    expect($query['filter'])->toContain('facets.pa_size = "400ml"');
    expect($query['distinct'])->toBe('parent_id');
    expect($query['attributesToRetrieve'])->toBe(['ID', 'card', 'parent_id']);
    expect($query)->not->toHaveKey('facets');
});

it('reads variants through the search scope once a term is searched', function (): void {
    $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']], query: 'lotion');

    $filter = QueryPlan::results($this->listing, $state, [])['filter'];

    expect($filter)->toContain('exclude-from-search');
    expect($filter)->toContain(VARIANT_RESULTS_VARIANT_DOCUMENTS);
});

it('sorts variants in stock first under a price sort only', function (): void {
    $size = [FakeVariantScopedListing::SIZE => ['400ml']];

    $byPrice = QueryPlan::results($this->listing, new ListingState($size, 'price_asc'), []);
    $byDate = QueryPlan::results($this->listing, new ListingState($size, 'newest'), []);

    expect($byPrice['sort'])->toBe(['in_stock:desc', 'price.min:asc']);
    expect($byDate['sort'])->toBe(['post_date:desc']);
});

it('reads products under a price range alone as woocommerce does', function (): void {
    $state = new ListingState(sort: 'price_asc', price: new Range(min: 30.0, max: 35.0));

    $query = QueryPlan::results($this->listing, $state, []);

    expect(QueryPlan::readsVariants($this->listing, $state))->toBeFalse();
    expect($query['filter'])->not->toContain(VARIANT_RESULTS_OF_VARIATIONS);
    expect($query['filter'])->toContain('price.min <= 35 AND price.max >= 30');
    expect($query)->not->toHaveKey('distinct');
    expect($query['sort'])->toBe(['price.min:asc']);
});

it('never reads variants of a listing without a document per variant', function (): void {
    $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']]);

    expect(QueryPlan::readsVariants(FakeListing::withPriceAndBrand(), $state))->toBeFalse();
});

it('measures once per product on the variants beside the variant results', function (): void {
    $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']], 'price_asc');

    $query = QueryPlan::measures($this->listing, $state, ['count:pa_size']);

    expect($query['filter'])->toContain(VARIANT_RESULTS_VARIANT_DOCUMENTS);
    expect($query['filter'])->toContain('facets.pa_size = "400ml"');
    expect($query['distinct'])->toBe('parent_id');
    expect($query['facets'])->toBe(['facets.product_brand', 'price.min', 'price.max']);
    expect($query['hitsPerPage'])->toBe(0);
    expect($query['page'])->toBe(1);
    expect($query)->not->toHaveKey('sort');
});

it('counts the sizes once per variant under the other filters', function (): void {
    $state = new ListingState(['product_brand' => ['acme']], price: new Range(min: 30.0, max: 35.0));

    $query = QueryPlan::measureWithout($this->listing, $state, variantResultsQueryKeyed($this->listing, 'count:pa_size'));

    expect($query['filter'])->toContain(VARIANT_RESULTS_VARIANT_DOCUMENTS);
    expect($query['filter'])->toContain('facets.product_brand = "acme"');
    expect($query['filter'])->toContain('price.min <= 35 AND price.max >= 30');
    expect($query)->not->toHaveKey('distinct');
    expect($query['facets'])->toBe(['facets.pa_size']);
});

it('counts a ticked size without its own clause', function (): void {
    $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']]);

    $query = QueryPlan::measureWithout($this->listing, $state, variantResultsQueryKeyed($this->listing, 'count:pa_size'));

    expect($query['filter'])->not->toContain('facets.pa_size');
    expect($query)->not->toHaveKey('distinct');
});

it('counts a shared facet once per product while a size is ticked', function (): void {
    $state = new ListingState(['product_brand' => ['acme'], FakeVariantScopedListing::SIZE => ['400ml']]);

    $query = QueryPlan::measureWithout($this->listing, $state, variantResultsQueryKeyed($this->listing, 'count:product_brand'));

    expect($query['filter'])->toContain(VARIANT_RESULTS_VARIANT_DOCUMENTS);
    expect($query['filter'])->toContain('facets.pa_size = "400ml"');
    expect($query['distinct'])->toBe('parent_id');
});

it('counts a shared facet on the products while no size is ticked', function (): void {
    $state = new ListingState(['product_brand' => ['acme']], price: new Range(max: 30.0));

    $query = QueryPlan::measureWithout($this->listing, $state, variantResultsQueryKeyed($this->listing, 'count:product_brand'));

    expect($query['filter'])->not->toContain(VARIANT_RESULTS_OF_VARIATIONS);
    expect($query)->not->toHaveKey('distinct');
});

it('bounds the price on every variant while a size is ticked', function (): void {
    $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']], price: new Range(max: 30.0));

    $query = QueryPlan::measureWithout($this->listing, $state, variantResultsQueryKeyed($this->listing, PriceQuery::KEY));

    expect($query['filter'])->toContain(VARIANT_RESULTS_VARIANT_DOCUMENTS);
    expect($query['filter'])->not->toContain('price.min <=');
    expect($query)->not->toHaveKey('distinct');
});

it('offers the facets of the variant taxonomies only', function (): void {
    $keys = array_map(static fn (FacetQuery $query): string => $query->key(), QueryPlan::variantFacetQueries($this->listing));

    expect($keys)->toBe(['count:pa_size']);
    expect(QueryPlan::variantFacetQueries(FakeListing::withPriceAndBrand()))->toBe([]);
});
