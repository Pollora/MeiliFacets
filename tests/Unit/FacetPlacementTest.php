<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PlacementRefused;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;

/*
 * The rules a template plays by, on facets the test declares itself — the host
 * project must never be able to break the module's suite by adding a facet.
 */

/**
 * @param  list<Facet>|null  $facets
 */
function facetPlacementListing(?array $facets = null): ResolvedListing
{
    return new ResolvedListing(
        new FakeListing($facets ?? [
            new Facet('product_brand', 'Brand', name: 'brand'),
            new Facet('pa_size', 'Size', name: 'size'),
        ]),
        new ListingState,
        new ListingSearch(new FakeSearchEngine, new DisjunctiveFacetCounter),
        new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),

        new EngineLimits(1000),
    );
}

/**
 * @param  list<Placeable>  $filters
 * @return list<string>
 */
function facetPlacementNamesOf(array $filters): array
{
    return array_map(static fn (Placeable $filter): string => $filter->name, $filters);
}

it('offers every facet when a template places none', function (): void {
    expect(facetPlacementNamesOf(facetPlacementListing()->placement()->remainingFilters()))->toBe(['brand', 'size']);
});

it('drops from the group what a template placed on its own', function (): void {
    $listing = facetPlacementListing();

    $listing->placement()->placeNamed('brand', Facet::class);

    expect(facetPlacementNamesOf($listing->placement()->remainingFilters()))->toBe(['size']);
});

// Two identical blocks would duplicate the inputs and the ids they carry.
it('refuses to render the same facet twice', function (): void {
    $listing = facetPlacementListing();
    $brand = array_find($listing->filters(), static fn (Placeable $filter): bool => $filter->name === 'brand');

    $listing->placement()->placeFilter($brand, Facet::class);

    $this->expectException(PlacementRefused::class);
    $this->expectExceptionMessageMatches('/Facet "brand" is rendered twice/');

    $listing->placement()->placeFilter($brand, Facet::class);
});

// The sort is a control like a facet: a drawer shows the one copy, never a second.
it('refuses to render the sort twice', function (): void {
    $listing = facetPlacementListing();

    $listing->placement()->placeSort();

    $this->expectException(PlacementRefused::class);
    $this->expectExceptionMessageMatches('/The sort of listing "fake" is rendered twice.*Render <x-meilifacets::listing.sort> once per page\\./');

    $listing->placement()->placeSort();
});

// The group takes what is left, so placing a facet after it places it twice.
it('refuses a facet placed after the group', function (): void {
    $listing = facetPlacementListing();

    foreach ($listing->placement()->remainingFilters() as $facet) {
        $listing->placement()->placeFilter($facet, Facet::class);
    }

    $this->expectException(PlacementRefused::class);

    $listing->placement()->placeNamed('brand', Facet::class);
});

it('names the facets it knows when asked for one it does not', function (): void {
    $this->expectException(PlacementRefused::class);
    $this->expectExceptionMessageMatches('/No facet named "colour".*brand, size/');

    facetPlacementListing()->placement()->placeNamed('colour', Facet::class);
});

// A facet that names nothing answers to its taxonomy.
it('finds a facet that was never named', function (): void {
    $listing = facetPlacementListing([new Facet('product_tag', 'Tag')]);

    expect($listing->placement()->placeNamed('product_tag', Facet::class)->name)->toBe('product_tag');
});

it('refuses two facets answering to the same name', function (): void {
    $this->expectException(PlacementRefused::class);
    $this->expectExceptionMessageMatches('/Facets "Aisles" and "Shelves" answer to the same name "product_cat"/');

    facetPlacementListing([
        new Facet('product_cat', 'Aisles'),
        new Facet('product_cat', 'Shelves'),
    ])->filters();
});

it('takes two facets on one taxonomy once a name tells them apart', function (): void {
    $listing = facetPlacementListing([
        new Facet('product_cat', 'Aisles', name: 'aisles'),
        new Facet('product_cat', 'Shelves'),
    ]);

    expect(facetPlacementNamesOf($listing->filters()))->toBe(['aisles', 'product_cat']);
});
