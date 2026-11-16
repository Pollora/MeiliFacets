<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\ListingSearch;

use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeFacetCounter;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeVariantScopedListing;

const RESULTS = 'results';

const COUNT = 'count:';

const BOUNDS = 'bounds';

const UNFILTERED = 'unfiltered';

const UNFILTERED_VARIANTS = 'unfiltered:variants';

function searchWith(FakeSearchEngine $engine): ListingSearch
{
    return new ListingSearch($engine, new DisjunctiveFacetCounter);
}

it('sends every search in a single request', function (): void {
    $engine = new FakeSearchEngine;
    $state = new ListingState(['product_brand' => ['acme']]);

    searchWith($engine)->run(FakeListing::withBrandAndCategory(), $state);

    expect($engine->calls)->toBe(1);
    expect(array_keys($engine->received[0]))->toBe([RESULTS, COUNT.'product_brand', UNFILTERED]);
});

it('counts the unfiltered listing only once a visitor narrows it', function (): void {
    $engine = new FakeSearchEngine;
    $listing = FakeListing::withBrandAndCategory();

    searchWith($engine)->run($listing, new ListingState(sort: 'price_asc', page: 3));
    searchWith($engine)->run($listing, new ListingState(query: 'coat'));

    expect(array_keys($engine->received[0]))->toBe([RESULTS]);
    expect(array_keys($engine->received[1]))->toBe([RESULTS, UNFILTERED]);
});

it('reads what the unfiltered listing offers apart from the counts', function (): void {
    $engine = new FakeSearchEngine([
        RESULTS => ['facetDistribution' => ['facets.product_cat' => ['coats' => 2]]],
        UNFILTERED => ['facetDistribution' => ['facets.product_cat' => ['coats' => 4, 'hats' => 3]]],
    ]);

    $results = searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState(query: 'coat'));

    expect($results->distribution('product_cat'))->toBe(['coats' => 2]);
    expect($results->unfilteredDistribution('product_cat'))->toBe(['coats' => 4, 'hats' => 3]);
});

it('has no unfiltered distribution for a listing nobody narrowed', function (): void {
    $engine = new FakeSearchEngine([
        RESULTS => ['facetDistribution' => ['facets.product_cat' => ['coats' => 2]]],
    ]);

    $results = searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState);

    expect($results->unfilteredDistribution('product_cat'))->toBeNull();
});

it('reads the cards of the main response', function (): void {
    $engine = new FakeSearchEngine([RESULTS => [
        'hits' => [['card' => ['title' => 'Coat']], ['card' => ['title' => 'Scarf']], ['no_card' => true]],
        'totalHits' => 27,
    ]]);

    $results = searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState);

    expect($results->total)->toBe(27);
    expect($results->cards())->toBe([['title' => 'Coat'], ['title' => 'Scarf']]);
});

it('hands each card the id of its document', function (): void {
    $engine = new FakeSearchEngine([RESULTS => [
        'hits' => [['ID' => 393, 'card' => ['title' => 'Coat', 'id' => 7]]],
    ]]);

    $results = searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState);

    expect($results->cards())->toBe([['title' => 'Coat', 'id' => 393]]);
});

it('hands a card read off a variant the id of its product', function (): void {
    $engine = new FakeSearchEngine([RESULTS => [
        'hits' => [['ID' => '393-1', 'parent_id' => 393, 'card' => ['title' => 'Coat']]],
    ]]);

    $results = searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState);

    expect($results->cards())->toBe([['title' => 'Coat', 'id' => 393]]);
});

it('measures off a search of its own once the results read variants', function (): void {
    $engine = new FakeSearchEngine([
        RESULTS => [
            'hits' => [],
            'totalHits' => 2,
            'facetDistribution' => ['facets.product_brand' => ['acme' => 1]],
        ],
        'measures' => [
            'facetDistribution' => ['facets.product_brand' => ['acme' => 4]],
            'facetStats' => ['price.min' => ['min' => 12.0, 'max' => 40.0]],
        ],
    ]);

    $state = new ListingState([FakeVariantScopedListing::SIZE => ['400ml']]);
    $results = searchWith($engine)->run(new FakeVariantScopedListing, $state);

    $this->assertArrayHasKey('measures', $engine->received[0]);
    expect($results->distributions['product_brand'])->toBe(['acme' => 4]);
    expect($results->facetStats['price.min'])->toBe(['min' => 12.0, 'max' => 40.0]);
    expect($results->total)->toBe(2);
});

it('reads a constrained facet from its own response', function (): void {
    $state = new ListingState(['product_brand' => ['acme']]);
    $engine = new FakeSearchEngine([
        RESULTS => ['facetDistribution' => ['facets.product_cat' => ['coats' => 2]]],
        COUNT.'product_brand' => [
            'facetDistribution' => ['facets.product_brand' => ['acme' => 2, 'globex' => 5]],
        ],
    ]);

    $results = searchWith($engine)->run(FakeListing::withBrandAndCategory(), $state);

    expect($results->distribution('product_brand'))->toBe(['acme' => 2, 'globex' => 5]);
    expect($results->distribution('product_cat'))->toBe(['coats' => 2]);
});

it('reads the bounds from the search that lifted the price', function (): void {
    $engine = new FakeSearchEngine([
        RESULTS => ['facetStats' => ['price.min' => ['min' => 28.0], 'price.max' => ['max' => 109.0]]],
        BOUNDS => ['facetStats' => ['price.min' => ['min' => 0.0], 'price.max' => ['max' => 199.0]]],
    ]);
    $state = new ListingState(price: new Range(55.0, 120.0));

    $results = searchWith($engine)->run(FakeListing::withPriceAndBrand(), $state);

    expect($results->facetStats)->toBe(['price.min' => ['min' => 0.0], 'price.max' => ['max' => 199.0]]);
});

it('reads the bounds off the main response while no range is held', function (): void {
    $engine = new FakeSearchEngine([
        RESULTS => ['facetStats' => ['price.min' => ['min' => 0.0]]],
    ]);

    $results = searchWith($engine)->run(FakeListing::withPriceAndBrand(), new ListingState);

    expect(array_keys($engine->received[0]))->toBe([RESULTS]);
    expect($results->facetStats)->toBe(['price.min' => ['min' => 0.0]]);
});

it('counts what each filtering sort would keep', function (): void {
    $engine = new FakeSearchEngine([RESULTS => ['facetDistribution' => ['price.onsale' => ['true' => 7, 'false' => 40]]]]);

    $results = searchWith($engine)->run(FakeListing::withPromotions(), new ListingState);

    expect($results->sortMatches)->toBe(['on_sale' => 7]);
});

it('counts nothing kept when the engine reports no match', function (): void {
    $engine = new FakeSearchEngine([RESULTS => ['facetDistribution' => ['price.onsale' => ['false' => 47]]]]);

    $results = searchWith($engine)->run(FakeListing::withPromotions(), new ListingState);

    expect($results->sortMatches)->toBe(['on_sale' => 0]);
});

it('counts the unfiltered listing under a sort that filters', function (): void {
    $engine = new FakeSearchEngine;
    $listing = FakeListing::withPromotions();

    searchWith($engine)->run($listing, new ListingState(sort: 'price_asc'));
    searchWith($engine)->run($listing, new ListingState(sort: 'on_sale'));

    expect(array_keys($engine->received[0]))->not->toContain(UNFILTERED);
    expect(array_keys($engine->received[1]))->toContain(UNFILTERED);
});

it('survives an engine answering nothing', function (): void {
    $results = searchWith(new FakeSearchEngine)->run(FakeListing::withBrandAndCategory(), new ListingState);

    expect($results->total)->toBe(0);
    expect($results->cards())->toBe([]);
    expect($results->distribution('product_brand'))->toBe([]);
});

it('counts separately the facet the counter measures separately', function (): void {
    $engine = new FakeSearchEngine;

    searchWith($engine)->run(FakeListing::withBrandAndCategory(), new ListingState(['product_brand' => ['acme']]));

    expect($engine->received[0][RESULTS]['facets'])->toBe(['facets.product_cat']);
    expect($engine->received[0][COUNT.'product_brand']['facets'])->toBe(['facets.product_brand']);
});

it('counts on the main search what the counter leaves to it', function (): void {
    $engine = new FakeSearchEngine;

    new ListingSearch($engine, new FakeFacetCounter)
        ->run(FakeListing::withBrandAndCategory(), new ListingState(['product_brand' => ['acme']]));

    expect(array_keys($engine->received[0]))->toBe([RESULTS, UNFILTERED]);
    expect($engine->received[0][RESULTS]['facets'])->toBe(['facets.product_brand', 'facets.product_cat']);
});

it('never counts a facet twice', function (): void {
    $engine = new FakeSearchEngine;
    $state = new ListingState(['product_brand' => ['acme'], 'product_cat' => ['coats']], price: new Range(55.0));

    searchWith($engine)->run(FakeListing::withPriceAndBrand(), $state);

    $onMain = $engine->received[0][RESULTS]['facets'];

    foreach ($engine->received[0] as $key => $query) {
        if ($key !== RESULTS && $key !== UNFILTERED) {
            $this->assertSame([], array_intersect($query['facets'], $onMain), "\"{$key}\" is counted twice.");
        }
    }
});

it('never asks the main search for bounds it measures separately', function (): void {
    $engine = new FakeSearchEngine;
    $listing = FakeListing::withPriceAndBrand();

    searchWith($engine)->run($listing, new ListingState);
    searchWith($engine)->run($listing, new ListingState(price: new Range(55.0)));

    expect($engine->received[0][RESULTS]['facets'])->toBe(['facets.product_brand', 'price.min', 'price.max']);
    expect($engine->received[1][RESULTS]['facets'])->toBe(['facets.product_brand']);
});

it('counts the variant facets apart while nothing is ticked', function (): void {
    $engine = new FakeSearchEngine([
        RESULTS => ['facetDistribution' => ['facets.product_brand' => ['acme' => 3]]],
        COUNT.FakeVariantScopedListing::SIZE => ['facetDistribution' => ['facets.pa_size' => ['400ml' => 2]]],
    ]);

    $results = searchWith($engine)->run(new FakeVariantScopedListing, new ListingState);

    expect(array_keys($engine->received[0]))->toBe([RESULTS, COUNT.FakeVariantScopedListing::SIZE]);
    expect($engine->received[0][RESULTS]['facets'])->not->toContain('facets.pa_size');
    expect($results->distributions[FakeVariantScopedListing::SIZE])->toBe(['400ml' => 2]);
});

it('never counts a facet twice on a listing with variants', function (): void {
    $engine = new FakeSearchEngine;
    $state = new ListingState(['product_brand' => ['acme'], FakeVariantScopedListing::SIZE => ['400ml']], price: new Range(55.0));

    searchWith($engine)->run(new FakeVariantScopedListing, $state);

    $counted = [];

    foreach ($engine->received[0] as $key => $query) {
        if (! str_starts_with($key, UNFILTERED)) {
            $facets = $query['facets'] ?? [];
            $this->assertSame([], array_intersect($facets, $counted), "\"{$key}\" is counted twice.");
            $counted = [...$counted, ...$facets];
        }
    }
});

it('bounds the price on every variant once the results read variants', function (): void {
    $engine = new FakeSearchEngine;

    searchWith($engine)->run(new FakeVariantScopedListing, new ListingState([FakeVariantScopedListing::SIZE => ['400ml']]));

    $bounds = $engine->received[0][BOUNDS] ?? [];

    expect($bounds['facets'] ?? null)->toBe(['price.min', 'price.max']);
    $this->assertArrayNotHasKey('distinct', $bounds);
    expect($engine->received[0]['measures']['facets'])->not->toContain('price.min');
});

/** A product declaring L without a variation in L carries it on its own document, never on a variant's. */
it('offers a variation facet only the values a variant carries', function (): void {
    $engine = new FakeSearchEngine([
        UNFILTERED => ['facetDistribution' => ['facets.product_brand' => ['acme' => 1], 'facets.pa_size' => ['s' => 1, 'l' => 1]]],
        UNFILTERED_VARIANTS => ['facetDistribution' => ['facets.pa_size' => ['s' => 1]]],
    ]);

    $results = searchWith($engine)->run(new FakeVariantScopedListing, new ListingState(['product_brand' => ['acme']]));
    $searches = $engine->received[0];

    expect($results->unfilteredDistribution(FakeVariantScopedListing::SIZE))->toBe(['s' => 1]);
    expect($results->unfilteredDistribution('product_brand'))->toBe(['acme' => 1]);
    expect($searches[UNFILTERED_VARIANTS]['facets'])->toBe(['facets.pa_size']);
    expect($searches[UNFILTERED_VARIANTS]['filter'])->toContain('NOT document_kind = "parent"');
    expect($searches[UNFILTERED]['facets'])->not->toContain('facets.pa_size');
});
