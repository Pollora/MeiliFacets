<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\WooCommerce\ShopTaxLocation;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** The WooCommerce doubles live in the shared process: only a process of its own is still without WooCommerce. */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ShopTaxLocationTest extends TestCase
{
    #[Test]
    public function it_only_reads_on_a_site_without_woocommerce(): void
    {
        $this->assertFalse(function_exists('wc_get_product'));
        $this->assertSame('card', new ShopTaxLocation()->during(static fn (): string => 'card'));
    }
}
