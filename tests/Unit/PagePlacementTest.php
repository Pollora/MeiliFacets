<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\PagePlacement;

use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\PagePlacement;
use Modules\MeiliFacets\Listing\PlacementRefused;

/**
 * @param  list<Placeable>  $filters
 */
function placementOf(array $filters): PagePlacement
{
    return new PagePlacement('products', $filters, static fn (): bool => true);
}

function facet(string $name): Facet
{
    return new Facet('pa_'.$name, ucfirst($name), name: $name);
}

/**
 * @param  list<Placeable>  $filters
 * @return list<string>
 */
function namesOf(array $filters): array
{
    return array_map(static fn (Placeable $filter): string => $filter->name, $filters);
}

it('refuses the same facet twice', function (): void {
    $placement = placementOf([facet('brand')]);
    $placement->placeFilter(facet('brand'), Facet::class);

    $this->expectException(PlacementRefused::class);
    $this->expectExceptionMessageMatches('/Facet "brand" is rendered twice/');

    $placement->placeFilter(facet('brand'), Facet::class);
});

it('refuses the sort twice', function (): void {
    $placement = placementOf([]);
    $placement->placeSort();

    $this->expectException(PlacementRefused::class);
    $this->expectExceptionMessage(
        'The sort of listing "products" is rendered twice on this page: its list and ids '
        .'would be duplicated. Render <x-meilifacets::listing.sort> once per page.'
    );

    $placement->placeSort();
});

/** A project is free to name a facet after the sort, or after its listing. */
it('keeps a facet named like the sort apart from the sort', function (): void {
    $placement = placementOf([]);

    foreach (['sort', 'products'] as $name) {
        $placement->placeFilter(facet($name), Facet::class);
    }
    $placement->placeSort();

    $this->expectNotToPerformAssertions();
});

it('leaves out of the remaining filters only what was placed on its own', function (): void {
    $filters = [facet('brand'), facet('size'), facet('colour')];
    $placement = placementOf($filters);

    $placement->placeNamed('brand', Facet::class);
    $placement->placeFilter($filters[2], Facet::class);

    expect(namesOf($placement->remainingFilters()))->toBe(['size', 'colour']);
});

/** Past the row, a filter only counts while the catalogue offers it: one it leaves empty lets the next move up. */
it('puts past the row what follows its limit of filters in the catalogue', function (): void {
    $filters = [facet('brand'), facet('size'), facet('colour')];
    $placement = new PagePlacement('products', $filters, static fn (Placeable $filter): bool => $filter->name !== 'brand');

    $placement->startRow(1);
    array_map(static fn (Facet $filter): Placeable => $placement->placeFilter($filter, Facet::class), $filters);

    expect(namesOf($placement->filtersBeyondRow()))->toBe(['colour']);
    expect($placement->catalogFilterCountSince(0))->toBe(2);

    $placement->endRow();
    expect($placement->filtersBeyondRow())->toBe([]);
});
