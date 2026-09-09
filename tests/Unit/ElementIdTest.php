<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\View\ElementId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ElementIdTest extends TestCase
{
    /** Two listings on one page must not describe each other's controls. */
    #[Test]
    public function it_carries_the_listing_name(): void
    {
        $ids = new ElementId('products');

        $this->assertSame('meilifacets-products-sort-trigger', $ids->sortTrigger());
        $this->assertSame('meilifacets-products-sort-option-newest', $ids->sortOption('newest'));
        $this->assertSame('meilifacets-products-facet-volume--panel', $ids->facetPanel('volume'));
        $this->assertSame('meilifacets-products-facet-volume--count-100ml', $ids->facetCount('volume', '100ml'));
    }

    #[Test]
    public function it_keeps_two_listings_apart(): void
    {
        $this->assertNotSame(
            new ElementId('products')->sortList(),
            new ElementId('articles')->sortList()
        );
    }

    #[Test]
    public function it_never_builds_one_identifier_from_two_different_elements(): void
    {
        $ids = new ElementId('products');
        $source = [];

        foreach ($this->hostileNames() as $facet) {
            $source[$ids->facetPanel($facet)][] = "panel({$facet})";

            foreach ($this->termSlugs() as $slug) {
                $source[$ids->facetCount($facet, $slug)][] = "count({$facet}, {$slug})";
            }
        }

        foreach ($this->hostileNames() as $key) {
            $source[$ids->sortOption($key)][] = "sortOption({$key})";
        }

        foreach ([$ids->sortLabel(), $ids->sortTrigger(), $ids->sortList()] as $fixed) {
            $source[$fixed][] = 'fixed';
        }

        $this->assertSame([], array_filter($source, static fn (array $from): bool => count($from) > 1));
    }

    /** @return list<string> */
    private function hostileNames(): array
    {
        return array_values(array_unique(array_merge($this->combine('-'), $this->combine('--'))));
    }

    /** @return list<string> */
    private function termSlugs(): array
    {
        return $this->combine('-');
    }

    /** @return list<string> */
    private function combine(string $glue): array
    {
        $words = ['facet', 'panel', 'count', 'x'];
        $built = $words;

        for ($depth = 2; $depth <= 3; $depth++) {
            foreach ($built as $left) {
                foreach ($words as $right) {
                    $built[] = $left.$glue.$right;
                }
            }
        }

        return array_values(array_unique($built));
    }
}
