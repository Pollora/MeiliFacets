<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\View\RangeHandle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Standalone, WooCommerce is absent: `Money` writes two decimals and a thousands comma. */
final class RangeHandleTest extends TestCase
{
    #[Test]
    public function it_writes_the_price_it_holds(): void
    {
        $this->assertSame('1,234.50', $this->handle(1234.5, 1234.5)->written());
    }

    /** An open end is drawn at the floor, yet the price it writes is the one it holds. */
    #[Test]
    public function it_writes_the_price_it_holds_rather_than_the_one_it_is_drawn_at(): void
    {
        $this->assertSame('5.00', $this->handle(5.0, null)->written());
        $this->assertSame('5.00', $this->handle(5.0, 20.0)->written());
    }

    private function handle(float $value, ?float $shown): RangeHandle
    {
        return new RangeHandle(PriceBound::Min, 'min_price', $value, $shown, 0.0, 2000.0, 0.0, new Money);
    }
}
