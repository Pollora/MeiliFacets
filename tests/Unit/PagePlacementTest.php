<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\PagePlacement;
use Modules\MeiliFacets\Listing\PlacementRefused;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PagePlacementTest extends TestCase
{
    #[Test]
    public function it_refuses_the_same_facet_twice(): void
    {
        $placement = $this->placementOf([$this->facet('brand')]);
        $placement->placeFilter($this->facet('brand'), Facet::class);

        $this->expectException(PlacementRefused::class);
        $this->expectExceptionMessageMatches('/Facet "brand" is rendered twice/');

        $placement->placeFilter($this->facet('brand'), Facet::class);
    }

    #[Test]
    public function it_refuses_the_sort_twice(): void
    {
        $placement = $this->placementOf([]);
        $placement->placeSort();

        $this->expectException(PlacementRefused::class);
        $this->expectExceptionMessage(
            'The sort of listing "products" is rendered twice on this page: its list and ids '
            .'would be duplicated. Render <x-meilifacets::listing.sort> once per page.'
        );

        $placement->placeSort();
    }

    /** A project is free to name a facet after the sort, or after its listing. */
    #[Test]
    public function it_keeps_a_facet_named_like_the_sort_apart_from_the_sort(): void
    {
        $placement = $this->placementOf([]);

        foreach (['sort', 'products'] as $name) {
            $placement->placeFilter($this->facet($name), Facet::class);
        }
        $placement->placeSort();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_leaves_out_of_the_remaining_filters_only_what_was_placed_on_its_own(): void
    {
        $filters = [$this->facet('brand'), $this->facet('size'), $this->facet('colour')];
        $placement = $this->placementOf($filters);

        $placement->placeNamed('brand', Facet::class);
        $placement->placeFilter($filters[2], Facet::class);

        $this->assertSame(['size', 'colour'], $this->namesOf($placement->remainingFilters()));
    }

    /** Past the row, a filter only counts while the catalogue offers it: one it leaves empty lets the next move up. */
    #[Test]
    public function it_puts_past_the_row_what_follows_its_limit_of_filters_in_the_catalogue(): void
    {
        $filters = [$this->facet('brand'), $this->facet('size'), $this->facet('colour')];
        $placement = new PagePlacement('products', $filters, static fn (Placeable $filter): bool => $filter->name !== 'brand');

        $placement->startRow(1);
        array_map(static fn (Facet $filter): Placeable => $placement->placeFilter($filter, Facet::class), $filters);

        $this->assertSame(['colour'], $this->namesOf($placement->filtersBeyondRow()));
        $this->assertSame(2, $placement->catalogFilterCountSince(0));

        $placement->endRow();
        $this->assertSame([], $placement->filtersBeyondRow());
    }

    /**
     * @param  list<Placeable>  $filters
     */
    private function placementOf(array $filters): PagePlacement
    {
        return new PagePlacement('products', $filters, static fn (): bool => true);
    }

    private function facet(string $name): Facet
    {
        return new Facet('pa_'.$name, ucfirst($name), name: $name);
    }

    /**
     * @param  list<Placeable>  $filters
     * @return list<string>
     */
    private function namesOf(array $filters): array
    {
        return array_map(static fn (Placeable $filter): string => $filter->name, $filters);
    }
}
