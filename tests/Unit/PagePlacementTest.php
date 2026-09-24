<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Contracts\Placeable;
use Modules\MeiliFacets\Listing\Facet;
use Modules\MeiliFacets\Listing\PagePlacement;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PagePlacementTest extends TestCase
{
    #[Test]
    public function it_refuses_the_same_facet_twice(): void
    {
        $placement = new PagePlacement('products');
        $placement->place($this->facet('brand'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Facet "brand" is rendered twice/');

        $placement->place($this->facet('brand'));
    }

    #[Test]
    public function it_refuses_the_sort_twice(): void
    {
        $placement = new PagePlacement('products');
        $placement->placeSort();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'The sort of listing "products" is rendered twice on this page: its list and ids '
            .'would be duplicated. Render <x-meilifacets::sort> once per page.'
        );

        $placement->placeSort();
    }

    /** A project is free to name a facet after the sort, or after its listing. */
    #[Test]
    public function it_keeps_a_facet_named_like_the_sort_apart_from_the_sort(): void
    {
        $placement = new PagePlacement('products');

        foreach (['sort', 'products'] as $name) {
            $placement->place($this->facet($name));
        }
        $placement->placeSort();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function it_leaves_out_of_the_remaining_facets_only_what_was_placed_apart(): void
    {
        $placement = new PagePlacement('products');
        $filters = [$this->facet('brand'), $this->facet('size'), $this->facet('colour')];

        $placement->placeApart($filters[0]);
        $placement->place($filters[2]);

        $this->assertSame(['size', 'colour'], $this->namesOf($placement->remaining($filters)));
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
