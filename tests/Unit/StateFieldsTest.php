<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\StateFields;

use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\PriceFilter;
use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Search\DisjunctiveFacetCounter;
use Modules\MeiliFacets\Search\EngineLimits;
use Modules\MeiliFacets\Search\ListingSearch;
use Modules\MeiliFacets\Search\ResolvedListing;
use Modules\MeiliFacets\Support\UrlParameters;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeListing;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeSearchEngine;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;
use Modules\MeiliFacets\View\HiddenField;
use Modules\MeiliFacets\View\StateFields;

// What a search form sends again of the state, so that searching keeps the filters and the order in force.

/**
 * @param  array<string, string>  $reserved
 * @return list<HiddenField>
 */
function fieldsOf(ListingState $state, array $reserved = []): array
{
    return new StateFields(new UrlParameters(['product_brand' => 'brand'], $reserved))->of(new ResolvedListing(
        new FakeListing([
            new Facet('product_brand', 'Brand', name: 'brand'),
            new PriceFilter('Price'),
            new Facet('pa_size', 'Size', name: 'size'),
        ]),
        $state,
        new ListingSearch(new FakeSearchEngine, new DisjunctiveFacetCounter),
        new FacetValues(new FakeTermLabels, new FakeTermScope, new FakeDefaultTerms),
        new EngineLimits(1000),
    ));
}

/**
 * @param  list<HiddenField>  $fields
 * @return list<array{string, string}>
 */
function pairsOf(array $fields): array
{
    return array_map(static fn (HiddenField $field): array => [$field->name, $field->value], $fields);
}

it('sends nothing while nothing is held', function (): void {
    expect(fieldsOf(new ListingState))->toBe([]);
});

it('sends the values of a facet in one field under its url parameter', function (): void {
    $fields = fieldsOf(new ListingState(['product_brand' => ['acme', 'globex']]));

    expect(pairsOf($fields))->toBe([['brand', 'acme,globex']]);
});

/** An unmapped taxonomy takes the prefixed name: its bare name is a WordPress query var. */
it('names an unmapped taxonomy by its prefixed parameter', function (): void {
    $fields = fieldsOf(new ListingState(['pa_size' => ['large']]));

    expect(pairsOf($fields))->toBe([[UrlParameters::UNMAPPED_PREFIX.'pa_size', 'large']]);
});

it('leaves out a facet with nothing ticked', function (): void {
    $fields = fieldsOf(new ListingState(['product_brand' => [], 'pa_size' => ['large']]));

    expect(pairsOf($fields))->toBe([[UrlParameters::UNMAPPED_PREFIX.'pa_size', 'large']]);
});

/** The state may name a taxonomy the listing does not filter on: the URL wrote it, the form must not repeat it. */
it('leaves out a taxonomy the listing has no facet for', function (): void {
    expect(fieldsOf(new ListingState(['product_tag' => ['sale']])))->toBe([]);
});

it('sends the sort in force under its reserved parameter', function (): void {
    $fields = fieldsOf(new ListingState(sort: 'price_asc'), ['sort' => 'tri']);

    expect(pairsOf($fields))->toBe([['tri', 'price_asc']]);
});

it('sends only the price bound that was asked', function (): void {
    expect(pairsOf(fieldsOf(new ListingState(price: new Range(10.0)))))->toBe([['min_price', '10']]);
    expect(pairsOf(fieldsOf(new ListingState(price: new Range(max: 50.0)))))->toBe([['max_price', '50']]);
});

/** A bound is written as the URL would write it: no trailing zeros, no exponent. */
it('writes a price bound without trailing zeros', function (): void {
    $fields = fieldsOf(new ListingState(price: new Range(0.0000001, 10.5)));

    expect(pairsOf($fields))->toBe([['min_price', '0'], ['max_price', '10.5']]);
});

/** The searched term and the page are left out: a new search starts from its first page, with its own term. */
it('sends the facets then the sort then the price and neither the term nor the page', function (): void {
    $state = new ListingState(
        ['pa_size' => ['large'], 'product_brand' => ['acme']],
        sort: 'newest',
        page: 3,
        query: 'coat',
        price: new Range(10.0, 50.0),
    );

    expect(pairsOf(fieldsOf($state)))->toBe([
        ['brand', 'acme'],
        [UrlParameters::UNMAPPED_PREFIX.'pa_size', 'large'],
        ['sort', 'newest'],
        ['min_price', '10'],
        ['max_price', '50'],
    ]);
});
