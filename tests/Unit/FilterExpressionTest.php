<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\FilterExpression;
use Modules\MeiliFacets\Listing\Range;

beforeEach(function (): void {
    $this->brand = new Facet('product_brand', 'Brand');
});

it('leaves a single value unwrapped', function (): void {
    expect(FilterExpression::facet($this->brand, ['acme']))->toBe('facets.product_brand = "acme"');
});

it('joins the values of one facet with or', function (): void {
    expect(FilterExpression::facet($this->brand, ['acme', 'globex']))
        ->toBe('(facets.product_brand = "acme" OR facets.product_brand = "globex")');
});

it('returns nothing for a facet left empty', function (): void {
    expect(FilterExpression::facet($this->brand, []))->toBe('');
});

it('escapes a quote so it cannot close the value', function (): void {
    expect(FilterExpression::facet($this->brand, ['5" band']))->toBe('facets.product_brand = "5\\" band"');
});

// A value ending in a backslash would otherwise escape the closing quote and
// let the rest of the URL through as filter syntax.
it('escapes a trailing backslash', function (): void {
    expect(FilterExpression::facet($this->brand, ['back\\']))->toBe('facets.product_brand = "back\\\\"');
});

it('keeps an injected clause inside the value', function (): void {
    expect(FilterExpression::facet($this->brand, ['x" OR post_status = "draft']))
        ->toBe('facets.product_brand = "x\\" OR post_status = \\"draft"');
});

/*
 * `field != value` drops every document missing the field: measured on the
 * project index, it returned zero of twelve products.
 */
it('negates an equality rather than using a difference', function (): void {
    expect(FilterExpression::without('facets.product_visibility', 'exclude-from-catalog'))
        ->toBe('NOT facets.product_visibility = "exclude-from-catalog"');
});

it('drops empty clauses when joining', function (): void {
    expect(FilterExpression::all(['post_type = "product"', '', 'x = "y"']))
        ->toBe('post_type = "product" AND x = "y"');
});

/*
 * Two intervals overlap unless one ends before the other starts. Writing it as
 * `min >= asked AND max <= asked` would keep only products that fit inside the
 * range, losing every one that merely reaches into it.
 */
it('asks the engine for an overlap', function (): void {
    expect(FilterExpression::overlapping(new Range(40.0, 70.0)))->toBe('price.min <= 70 AND price.max >= 40');
});

it('leaves an open end unconstrained', function (): void {
    expect(FilterExpression::overlapping(new Range(min: 40.0)))->toBe('price.max >= 40');
    expect(FilterExpression::overlapping(new Range(max: 70.0)))->toBe('price.min <= 70');
    expect(FilterExpression::overlapping(new Range))->toBe('');
});

/*
 * Fixed notation to four decimals: `(string) 1.0E-9` is not a filter, and no
 * currency carries more than three. A bound is written as asked, not rounded
 * to the money format, so a hand-typed `99.999` still means what it says.
 */
it('writes a bound the engine can read', function (): void {
    expect(FilterExpression::overlapping(new Range(max: 99.999)))->toBe('price.min <= 99.999');
    expect(FilterExpression::overlapping(new Range(max: 0.0)))->toBe('price.min <= 0');
    expect(FilterExpression::overlapping(new Range(max: 12.50)))->toBe('price.min <= 12.5');
    expect(FilterExpression::overlapping(new Range(max: 1e6)))->toBe('price.min <= 1000000');
});

it('writes a list of integers bare', function (): void {
    expect(FilterExpression::oneOf('parent_id', [125, 126]))->toBe('parent_id IN [125, 126]');
});

it('joins clauses with or like all joins them with and', function (): void {
    expect(FilterExpression::any(['a = 1', '', 'b = 2']))->toBe('(a = 1 OR b = 2)');
    expect(FilterExpression::any(['', 'a = 1']))->toBe('a = 1');
    expect(FilterExpression::any([]))->toBe('');
});
