<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\RangeHandle;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\View\RangeHandle;

// Standalone, WooCommerce is absent: `Money` writes two decimals and a thousands comma.

function handle(float $value, ?float $shown): RangeHandle
{
    return new RangeHandle(PriceBound::Min, 'min_price', $value, $shown, 0.0, 2000.0, 0.0, new Money);
}

it('writes the price it holds', function (): void {
    expect(handle(1234.5, 1234.5)->written())->toBe('1,234.50');
});

/** An open end is drawn at the floor, yet the price it writes is the one it holds. */
it('writes the price it holds rather than the one it is drawn at', function (): void {
    expect(handle(5.0, null)->written())->toBe('5.00');
    expect(handle(5.0, 20.0)->written())->toBe('5.00');
});
