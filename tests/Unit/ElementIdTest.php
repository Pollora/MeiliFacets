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
        $this->assertSame('meilifacets-products-sort-newest', $ids->sortOption('newest'));
        $this->assertSame('meilifacets-products-pa_contenance-100ml', $ids->facetCount('pa_contenance', '100ml'));
    }

    #[Test]
    public function it_keeps_two_listings_apart(): void
    {
        $this->assertNotSame(
            new ElementId('products')->sortList(),
            new ElementId('articles')->sortList()
        );
    }
}
