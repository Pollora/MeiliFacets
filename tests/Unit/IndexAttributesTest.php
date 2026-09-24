<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\ConfiguredIndexAttributes;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\WooCommerceIndexAttributes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IndexAttributesTest extends TestCase
{
    #[Test]
    public function it_contributes_nothing_without_a_plugin(): void
    {
        $attributes = new EmptyIndexAttributes;

        $this->assertSame([], $attributes->filterable());
        $this->assertSame([], $attributes->sortable());
    }

    #[Test]
    public function it_declares_the_woocommerce_metas_under_their_document_path(): void
    {
        $this->assertSame(['metas._price', 'metas._stock_status'], $this->filterable('metas.'));
    }

    /** None of the three exists in `postmeta`: the module computes them. */
    #[Test]
    public function it_declares_the_price_interval_outside_the_metas(): void
    {
        $this->assertSame(['price.min', 'price.max', 'price.onsale'], $this->filterable('price.'));
    }

    /** Ascending reads the low end, descending the high one — see `WooCommerceSorts`. */
    #[Test]
    public function it_sorts_on_both_ends_of_the_price_interval(): void
    {
        $this->assertSame(['price.min', 'price.max'], (new WooCommerceIndexAttributes)->sortable());
    }

    #[Test]
    public function it_exposes_nothing_beyond_the_module_by_default(): void
    {
        $this->assertSame([], (new EmptyIndexAttributes)->displayed());
        $this->assertSame([], (new WooCommerceIndexAttributes)->displayed());
    }

    #[Test]
    public function it_adds_the_fields_a_project_declared(): void
    {
        $attributes = new ConfiguredIndexAttributes(new WooCommerceIndexAttributes, ['post_excerpt', 'metas._sku']);

        $this->assertSame(['post_excerpt', 'metas._sku'], $attributes->displayed());
    }

    #[Test]
    public function it_leaves_the_other_declarations_untouched(): void
    {
        $attributes = new ConfiguredIndexAttributes(new WooCommerceIndexAttributes, ['post_excerpt']);

        $this->assertSame(
            ['metas._price', 'metas._stock_status', 'price.min', 'price.max', 'price.onsale'],
            $attributes->filterable()
        );
        $this->assertSame(['price.min', 'price.max'], $attributes->sortable());
    }

    /**
     * @return list<string>
     */
    private function filterable(string $prefix): array
    {
        return array_values(array_filter(
            (new WooCommerceIndexAttributes)->filterable(),
            static fn (string $path): bool => str_starts_with($path, $prefix)
        ));
    }
}
