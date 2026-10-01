<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\WooCommerceProductFields;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WooCommerceProductFieldsTest extends TestCase
{
    #[Test]
    public function it_ranks_the_brand_then_the_category_then_the_sku(): void
    {
        $fields = new WooCommerceProductFields(static fn (): bool => true);

        $this->assertSame(['labels.product_brand', 'labels.product_cat', 'metas._sku'], $fields->all());
    }

    #[Test]
    public function it_ranks_nothing_without_woocommerce(): void
    {
        $this->assertSame([], new WooCommerceProductFields(static fn (): bool => false)->all());
    }

    /** A hook wired before plugins load keeps its instance for the whole request (R-171). */
    #[Test]
    public function it_asks_whether_woocommerce_is_there_on_every_read(): void
    {
        $pluginIsActive = false;
        $fields = new WooCommerceProductFields(static function () use (&$pluginIsActive): bool {
            return $pluginIsActive;
        });

        $this->assertSame([], $fields->all());

        $pluginIsActive = true;

        $this->assertCount(3, $fields->all());
    }
}
