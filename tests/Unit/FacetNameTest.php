<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\FacetName;

use Modules\MeiliFacets\Listing\Facet;

enum FakeShopFacet: string
{
    case Brand = 'brand';
}

/** A facet that names nothing answers to its taxonomy, so nothing has to be declared to start. */
it('answers to its taxonomy by default', function (): void {
    expect(new Facet('product_brand', 'Brand')->name)->toBe('product_brand');
});

it('takes the name a project gives it', function (): void {
    expect(new Facet('product_brand', 'Brand', name: 'brand')->name)->toBe('brand');
});

/** A project names its facets in an enum, so a template never writes the string itself. */
it('reads the name off an enum', function (): void {
    expect(new Facet('product_brand', 'Brand', name: FakeShopFacet::Brand)->name)->toBe('brand');
});
