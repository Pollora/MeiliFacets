<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\DefaultTermVisibility;
use Modules\MeiliFacets\Enums\DisplayOrder;
use Modules\MeiliFacets\Listing\ChildTermsFacet;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FacetValue;
use Modules\MeiliFacets\Listing\FacetValues;
use Modules\MeiliFacets\Listing\ListingState;
use Modules\MeiliFacets\Listing\NameOrder;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeDefaultTerms;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermLabels;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;

/*
 * Labels come from WordPress, so these cases exercise the folding and the cap,
 * which are the parts that hold without it.
 */

function facetValuesValues(): FacetValues
{
    return new FacetValues(new FakeTermLabels(['a' => 'Acme']), new FakeTermScope, new FakeDefaultTerms);
}

/**
 * @return array<string, int>
 */
function facetValuesDistribution(int $count): array
{
    return array_combine(
        array_map(static fn (int $i): string => 'value-'.$i, range(1, $count)),
        array_fill(0, $count, 1)
    );
}

/** A taxonomy that has a fallback term, and one that has none. */
function facetValuesWithFallback(): FacetValues
{
    return new FacetValues(
        new FakeTermLabels([]),
        new FakeTermScope,
        new FakeDefaultTerms(['product_cat' => 'non-classe'])
    );
}

/**
 * @param  array<string, int>  $distribution
 * @return list<FacetValue>
 */
function facetValuesBuild(array $distribution, Facet $facet): array
{
    return facetValuesValues()->of($facet, $distribution, new ListingState);
}

it('folds everything past the visible limit', function (): void {
    $values = facetValuesBuild(facetValuesDistribution(14), new Facet('brand', 'Brand'));

    expect($values)->toHaveCount(14);
    expect(array_filter($values, static fn ($v): bool => ! $v->folded))->toHaveCount(10);
    expect($values[10]->folded)->toBeTrue();
    expect($values[9]->folded)->toBeFalse();
});

it('drops everything past the cap', function (): void {
    $values = facetValuesBuild(facetValuesDistribution(40), new Facet('brand', 'Brand'));

    expect($values)->toHaveCount(Facet::DEFAULT_CAP);
});

it('honours a facet that sets its own limits', function (): void {
    $values = facetValuesBuild(facetValuesDistribution(10), new Facet('brand', 'Brand', visible: 2, cap: 4));

    expect($values)->toHaveCount(4);
    expect(array_filter($values, static fn ($v): bool => ! $v->folded))->toHaveCount(2);
});

it('keeps the order the engine returned', function (): void {
    $values = facetValuesBuild(['b' => 9, 'a' => 5, 'c' => 1], new Facet('brand', 'Brand'));

    expect(array_map(static fn ($v): string => $v->slug, $values))->toBe(['b', 'a', 'c']);
});

it('marks the values the url holds', function (): void {
    $state = new ListingState(['brand' => ['a']]);
    $values = facetValuesValues()->of(new Facet('brand', 'Brand'), ['a' => 2, 'b' => 3], $state);

    expect($values[0]->selected)->toBeTrue();
    expect($values[1]->selected)->toBeFalse();
});

it('shows the values in the order the facet asked for', function (): void {
    $facet = new Facet('size', 'Size', order: new NameOrder);
    $values = facetValuesBuild(['500ml' => 9, '10ml' => 5, '50ml' => 1], $facet);

    expect(array_map(static fn ($v): string => $v->slug, $values))->toBe(['10ml', '50ml', '500ml']);
});

// What is read is the head of the declared order, not the best counted.
it('folds what the display order puts last', function (): void {
    $facet = new Facet('size', 'Size', visible: 2, order: new NameOrder);
    $values = facetValuesBuild(['b' => 9, 'z' => 8, 'a' => 1], $facet);

    $folded = array_column(array_filter($values, static fn ($v): bool => $v->folded), 'slug');

    expect(array_map(static fn ($v): string => $v->slug, $values))->toBe(['a', 'b', 'z']);
    expect(array_values($folded))->toBe(['z']);
});

// The two limits answer to two different masters: the engine spends the cap, the facet the fold.
it('spends the cap on the count and the fold on the order', function (): void {
    $facet = new Facet('size', 'Size', visible: 1, cap: 2, order: new NameOrder);
    $values = facetValuesBuild(['b' => 9, 'z' => 8, 'a' => 1], $facet);

    // "a" sorts first but counts last: the cap drops it before the order is applied.
    expect(array_map(static fn ($v): string => $v->slug, $values))->toBe(['b', 'z']);
    expect(array_map(static fn ($v): bool => $v->folded, $values))->toBe([false, true]);
});

// A held value the visitor cannot see is a filter they cannot lift.
it('never folds away a value the url holds', function (): void {
    $facet = new Facet('brand', 'Brand', visible: 1);
    $values = facetValuesValues()->of($facet, ['a' => 9, 'b' => 8, 'c' => 7], new ListingState(['brand' => ['c']]));

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['a', 'b', 'c']);
    expect(array_map(static fn (FacetValue $v): bool => $v->folded, $values))->toBe([false, true, false]);
});

it('keeps every value the unfiltered listing offers hidden without results', function (): void {
    $values = facetValuesValues()->of(new Facet('brand', 'Brand'), ['a' => 2], new ListingState(['cat' => ['x']]), ['a' => 5, 'b' => 4]);

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['a', 'b']);
    expect(array_map(static fn (FacetValue $v): int => $v->count, $values))->toBe([2, 0]);
    expect(array_map(static fn (FacetValue $v): bool => $v->folded, $values))->toBe([false, true]);
});

it('shows a held value that has no result left', function (): void {
    $values = facetValuesValues()->of(new Facet('brand', 'Brand'), ['a' => 2], new ListingState(['brand' => ['b']]), ['a' => 5, 'b' => 4]);

    expect(array_map(static fn (FacetValue $v): bool => $v->folded, $values))->toBe([false, false]);
});

it('gives the visible places to values with results', function (): void {
    $facet = new Facet('brand', 'Brand', visible: 1);
    $values = facetValuesValues()->of($facet, ['b' => 3, 'c' => 1], new ListingState(['cat' => ['x']]), ['a' => 9, 'b' => 8, 'c' => 7]);

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['a', 'b', 'c']);
    expect(array_map(static fn (FacetValue $v): bool => $v->folded, $values))->toBe([true, false, true]);
});

it('keeps a held value both caps left out', function (): void {
    $facet = new Facet('brand', 'Brand', cap: 1);
    $values = facetValuesValues()->of($facet, ['c' => 5, 'b' => 3], new ListingState(['brand' => ['b', 'forged']]), ['a' => 9, 'b' => 8]);

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['a', 'c', 'b']);
});

it('keeps the values the narrowed page counts beyond the unfiltered cap', function (): void {
    $facet = new Facet('brand', 'Brand', cap: 2);
    $values = facetValuesValues()->of($facet, ['tail' => 4], new ListingState(query: 'zebra'), ['big' => 90, 'mid' => 50, 'tail' => 4]);

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['big', 'mid', 'tail']);
    expect(array_map(static fn (FacetValue $v): bool => $v->folded, $values))->toBe([true, true, false]);
});

it('never shows a held value its scope or fallback leaves out', function (): void {
    $values = facetValuesWithFallback()->of(
        new Facet('product_cat', 'Category'),
        ['a' => 2],
        new ListingState(['product_cat' => ['non-classe']]),
        ['a' => 5, 'non-classe' => 9],
    );

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['a']);
});

// The taxonomy already carries an order a shop set; the module reads it rather than inventing one.
it('shows the values in the order the taxonomy declares', function (): void {
    $labels = new FakeTermLabels(['50ml' => '50ml', '5ml' => '5ml', '4g' => '4g']);
    $facet = new Facet('pa_size', 'Size', order: DisplayOrder::Declared);

    $values = new FacetValues($labels, new FakeTermScope, new FakeDefaultTerms)
        ->of($facet, ['4g' => 9, '50ml' => 5, '5ml' => 1], new ListingState);

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['50ml', '5ml', '4g']);
});

// PHP turns an all-digit array key into an integer: a slug such as `100` must come back as text.
it('keeps an all digit slug as text in the declared order', function (): void {
    $labels = new FakeTermLabels(['200' => '200 ml', '100' => '100 ml', 'xl' => 'XL']);
    $facet = new Facet('pa_size', 'Size', order: DisplayOrder::Declared);

    $values = new FacetValues($labels, new FakeTermScope, new FakeDefaultTerms)
        ->of($facet, ['100' => 9, 'xl' => 5, '200' => 1], new ListingState(['pa_size' => ['100']]));

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['200', '100', 'xl']);
    expect(array_map(static fn (FacetValue $v): bool => $v->selected, $values))->toBe([false, true, false]);
});

it('keeps an all digit slug as text in the engine order', function (): void {
    $values = facetValuesValues()->of(new Facet('pa_size', 'Size'), ['100' => 9, '200' => 1], new ListingState);

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['100', '200']);
});

// A slug the taxonomy no longer lists still has a count: it waits at the end.
it('sends a value the taxonomy does not list to the end', function (): void {
    $labels = new FakeTermLabels(['b' => 'B', 'a' => 'A']);
    $facet = new Facet('pa_size', 'Size', order: DisplayOrder::Declared);

    $values = new FacetValues($labels, new FakeTermScope, new FakeDefaultTerms)
        ->of($facet, ['orphan' => 9, 'a' => 5, 'b' => 1], new ListingState);

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['b', 'a', 'orphan']);
});

// The cap is spent on what the facet may show, not on what the engine returned.
it('caps a scoped facet after scoping it', function (): void {
    $values = new FacetValues(
        new FakeTermLabels([]),
        new FakeTermScope(['product_cat' => ['b', 'c']]),
        new FakeDefaultTerms
    )->of(
        new ChildTermsFacet('product_cat', 'Category', cap: 2),
        ['a' => 9, 'b' => 8, 'c' => 7, 'd' => 6],
        new ListingState
    );

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['b', 'c']);
});

// « Non classé » says a content was filed nowhere: that is not a way to browse.
it('drops the term a taxonomy falls back to', function (): void {
    $values = facetValuesWithFallback()->of(
        new Facet('product_cat', 'Category'),
        ['a' => 4, 'non-classe' => 1],
        new ListingState
    );

    expect(array_map(static fn (FacetValue $v): string => $v->slug, $values))->toBe(['a']);
});

// A taxonomy whose fallback is a term an editor chose keeps it.
it('keeps the fallback a facet asks to show', function (): void {
    $values = facetValuesWithFallback()->of(
        new Facet('product_cat', 'Category', defaultTerm: DefaultTermVisibility::Shown),
        ['a' => 4, 'non-classe' => 1],
        new ListingState
    );

    expect($values)->toHaveCount(2);
});

it('leaves a taxonomy without a fallback alone', function (): void {
    $values = facetValuesWithFallback()->of(
        new Facet('product_brand', 'Brand'),
        ['a' => 4, 'b' => 1],
        new ListingState
    );

    expect($values)->toHaveCount(2);
});
