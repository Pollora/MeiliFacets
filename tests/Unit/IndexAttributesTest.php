<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

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
        $attributes = new WooCommerceIndexAttributes;

        $this->assertSame(['metas._price', 'metas._stock_status'], $attributes->filterable());
    }

    #[Test]
    public function it_sorts_on_price_only(): void
    {
        $this->assertSame(['metas._price'], (new WooCommerceIndexAttributes)->sortable());
    }
}
