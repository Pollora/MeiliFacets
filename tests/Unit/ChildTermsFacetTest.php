<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\ChildTermsFacet;

use Modules\MeiliFacets\Listing\ChildTermsFacet;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Tests\Unit\Doubles\FakeTermScope;

const DISTRIBUTION = [
    'visage' => 30, 'serums' => 9, 'cremes-visage' => 7,
    'cheveux' => 15, 'shampoings' => 6,
];

function facet(): ChildTermsFacet
{
    return new ChildTermsFacet('product_cat', 'Category');
}

it('offers the level the visitor is on', function (): void {
    $scope = new FakeTermScope(['product_cat' => ['serums', 'cremes-visage']]);

    $kept = facet()->within(DISTRIBUTION, $scope);

    expect($kept)->toBe(['serums' => 9, 'cremes-visage' => 7]);
});

/** A flat facet stops narrowing once the path pins its taxonomy; this one goes on offering the level below. */
it('keeps narrowing under the term the path pins', function (): void {
    expect(facet()->narrowsUnder('product_cat'))->toBeTrue();
    expect(new Facet('product_cat', 'Category')->narrowsUnder('product_cat'))->toBeFalse();
    expect(new Facet('product_brand', 'Brand')->narrowsUnder('product_cat'))->toBeTrue();
    expect(new Facet('product_brand', 'Brand')->narrowsUnder(null))->toBeTrue();
});

/** Moving sideways belongs to a breadcrumb, not to a control that narrows. */
it('offers nothing on a term without children', function (): void {
    expect(facet()->within(DISTRIBUTION, new FakeTermScope))->toBe([]);
});

/** A child the engine counted nowhere is not invented. */
it('keeps only the children the engine counted', function (): void {
    $scope = new FakeTermScope(['product_cat' => ['serums', 'masques']]);

    expect(facet()->within(DISTRIBUTION, $scope))->toBe(['serums' => 9]);
});

test('a plain facet shows everything the engine returned', function (): void {
    $facet = new Facet('product_brand', 'Brand');

    expect($facet->within(DISTRIBUTION, new FakeTermScope))->toBe(DISTRIBUTION);
});
