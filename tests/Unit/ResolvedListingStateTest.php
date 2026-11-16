<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\ResolvedListingState;

use Modules\MeiliFacets\Contracts\Listing;
use Modules\MeiliFacets\Listing\CardVariant;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeVariantScopedListing;

// What the components read of the visitor's state, asked of the listing rather than reached through it.
const RESULTS = 'results';

beforeEach(function (): void {
    $this->brand = new Facet('product_brand', 'Brand', name: 'brand');
    $this->size = new Facet('pa_size', 'Size', name: 'size');
});

function listing(ListingState $state, FakeSearchEngine $engine = new FakeSearchEngine, ?Listing $listing = null): ResolvedListing
{
    return new ResolvedListing(
        $listing ?? new FakeListing([
            new Facet('product_brand', 'Brand', name: 'brand'),
            new Facet('pa_size', 'Size', name: 'size'),
        ]),
        $state,
        new ListingSearch($engine, new DisjunctiveFacetCounter),
        new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),

        new EngineLimits(1000),
    );
}

it('answers the values held in a facet', function (): void {
    $listing = listing(new ListingState(facets: ['product_brand' => ['acme', 'zest']]));

    expect($listing->selectedIn($this->brand))->toBe(['acme', 'zest']);
    expect($listing->selectedIn($this->size))->toBe([]);
});

it('answers the price asked and counts it as one filter', function (): void {
    $asked = new Range(10.0, 20.0);
    $listing = listing(new ListingState(price: $asked));

    expect($listing->state()->price)->toBe($asked);
    expect($listing->state()->priceFilterCount())->toBe(1);
    expect(listing(new ListingState)->state()->priceFilterCount())->toBe(0);
});

it('shows a card through its variant only on a listing with variant documents', function (): void {
    $variants = [
        new CardVariant(['pa_size' => ['large']], 39.0, ['size' => 'Large'])->toArray(),
        new CardVariant(['pa_size' => ['small']], 26.0, ['size' => 'Small'])->toArray(),
    ];
    $engine = new FakeSearchEngine([RESULTS => ['hits' => [['ID' => 125, 'card' => ['variants' => $variants]]]]]);

    $state = new ListingState(facets: ['pa_size' => ['large']]);

    expect(listing($state, $engine, new FakeVariantScopedListing)->cards())->toBe([['id' => 125, 'size' => 'Large']]);
    expect(listing($state, $engine)->cards())->toBe([['id' => 125]]);
});

/** Search engines read the product's address, not the one of the variant the filters point to. */
it('keeps the product card beside the card shown through a variant', function (): void {
    $variants = [new CardVariant(['pa_size' => ['large']], 39.0, ['url' => '/product/?size=large'])->toArray()];
    $card = ['url' => '/product/', 'variants' => $variants];
    $engine = new FakeSearchEngine([RESULTS => ['hits' => [['ID' => 125, 'card' => $card]]]]);

    $listing = listing(new ListingState(facets: ['pa_size' => ['large']]), $engine, new FakeVariantScopedListing);

    expect($listing->cards()[0]['url'])->toBe('/product/?size=large');
    expect($listing->projectedCards()[0]['url'])->toBe('/product/');
});
