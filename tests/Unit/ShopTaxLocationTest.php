<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\ShopTaxLocation;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ShopTaxLocationTest extends TestCase
{
    #[Test]
    public function it_only_reads_on_a_site_without_woocommerce(): void
    {
        if (function_exists('wc_get_product')) {
            $this->markTestSkipped('WooCommerce is loaded: another suite ran first.');
        }

        $this->assertSame('card', new ShopTaxLocation()->during(static fn (): string => 'card'));
    }
}
