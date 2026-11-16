<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\CatalogFilterCount;

use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\FacetQuery;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;

const RESULTS = 'results';

const UNFILTERED = 'unfiltered';

beforeEach(function (): void {
    $this->brand = new Facet('product_brand', 'Brand', name: 'brand');
    $this->size = new Facet('pa_size', 'Size', name: 'size');
    $this->price = new PriceFilter('Price');
});

function countPlacing(ResolvedListing $listing, Facet|PriceFilter ...$filters): int
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
function priceRange(float $min, float $max): array
{
    return [
        PriceField::Min->path() => ['min' => $min, 'max' => $max],
        PriceField::Max->path() => ['min' => $min, 'max' => $max],
    ];
}

/**
 * @param  array<string, array<string, mixed>>  $responses
 */
function listingOf(FakeListing $fake, ListingState $state, array $responses): ResolvedListing
{
    return new ResolvedListing(
        $fake,
        $state,
        new ListingSearch(new FakeSearchEngine($responses), new DisjunctiveFacetCounter),
        new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),

        new EngineLimits(1000),
    );
}

it('counts every facet with a value and the price with a range', function (): void {
    $listing = listingOf(new FakeListing([$this->brand, $this->size, $this->price]), new ListingState, [RESULTS => [
        'facetDistribution' => [$this->brand->field() => ['acme' => 3], $this->size->field() => ['large' => 2]],
        'facetStats' => priceRange(12.0, 40.0),
    ]]);

    expect(countPlacing($listing, $this->brand, $this->size, $this->price))->toBe(3);
});

it('counts only what was placed since the mark', function (): void {
    $listing = listingOf(new FakeListing([$this->brand, $this->size, $this->price]), new ListingState, [RESULTS => [
        'facetDistribution' => [$this->brand->field() => ['acme' => 3], $this->size->field() => ['large' => 2]],
        'facetStats' => priceRange(12.0, 40.0),
    ]]);
    $listing->placement()->placeFilter($this->price, PriceFilter::class);

    expect(countPlacing($listing, $this->brand))->toBe(1);
});

it('leaves out a facet without values and a price without a range', function (): void {
    $listing = listingOf(new FakeListing([$this->brand, $this->size, $this->price]), new ListingState, [RESULTS => [
        'facetDistribution' => [$this->brand->field() => ['acme' => 3]],
        'facetStats' => priceRange(20.0, 20.0),
    ]]);

    expect(countPlacing($listing, $this->brand, $this->size, $this->price))->toBe(1);
    expect($listing->isShown($this->size))->toBeFalse();
    expect($listing->isShown($this->price))->toBeFalse();
});

/** `R-49`: a held value stays on screen with no result left, so its facet still counts. */
it('counts a facet that holds a value without results', function (): void {
    $listing = listingOf(new FakeListing([$this->brand, $this->size, $this->price]), new ListingState(facets: ['product_brand' => ['acme']]), [
        FacetQuery::keyFor('product_brand') => ['facetDistribution' => []],
        UNFILTERED => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]],
    ]);

    expect($listing->isShown($this->brand))->toBeTrue();
    expect(countPlacing($listing, $this->brand))->toBe(1);
});

/** Filtered or not, the row holds the same filters: a cleared filter in the browser never moves it back. */
it('counts a facet the filters emptied but the catalog offers', function (): void {
    $listing = listingOf(new FakeListing([$this->brand, $this->size, $this->price]), new ListingState(facets: ['product_brand' => ['acme']]), [
        RESULTS => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]], 'facetStats' => priceRange(20.0, 20.0)],
        UNFILTERED => ['facetDistribution' => [$this->brand->field() => ['acme' => 3], $this->size->field() => ['large' => 2]]],
    ]);

    expect($listing->isShown($this->size))->toBeFalse();
    expect(countPlacing($listing, $this->brand, $this->size, $this->price))->toBe(3);
});

it('leaves out a facet the whole catalog leaves empty', function (): void {
    $listing = listingOf(new FakeListing([$this->brand, $this->size, $this->price]), new ListingState(facets: ['product_brand' => ['acme']]), [
        RESULTS => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]],
        UNFILTERED => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]],
    ]);

    expect(countPlacing($listing, $this->brand, $this->size))->toBe(1);
});

/** On a category page the flat category facet is placed but never searched: no tick may bring it into the row. */
it('leaves out a facet the listing does not measure whether filtered or not', function (): void {
    $category = new Facet('product_cat', 'Category', name: 'category');
    $pinned = new FakeListing([$this->brand, $category], unmeasured: ['product_cat']);
    $catalog = [RESULTS => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]]];
    $filtered = [...$catalog, UNFILTERED => ['facetDistribution' => [$this->brand->field() => ['acme' => 3]]]];

    $bare = countPlacing(listingOf($pinned, new ListingState, $catalog), $this->brand, $category);
    $ticked = countPlacing(listingOf($pinned, new ListingState(facets: ['product_brand' => ['acme']]), $filtered), $this->brand, $category);

    expect([$bare, $ticked])->toBe([1, 1]);
});

/** A listing with no term facet counts nothing on the whole catalogue: the price still holds its place once searched. */
it('keeps the price in the row of a listing without term facets once searched', function (): void {
    $priceOnly = new FakeListing([$this->price]);
    $searched = listingOf($priceOnly, new ListingState(query: 'cream'), [
        RESULTS => ['facetDistribution' => [], 'facetStats' => priceRange(20.0, 20.0)],
    ]);

    expect($searched->isShown($this->price))->toBeFalse();
    expect(countPlacing($searched, $this->price))->toBe(1);
});
