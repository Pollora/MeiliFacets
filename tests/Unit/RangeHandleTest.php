<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\RangeHandle;

use Modules\MeiliFacets\Enums\PriceBound;
use Modules\MeiliFacets\Support\Money;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use Modules\MeiliFacets\View\RangeHandle;

// WooCommerce formats the price through `wc_price()`, answered here with two decimals and a thousands comma:
// the doubles stay in the shared process, so a test cannot rely on WooCommerce being absent.
beforeEach(function (): void {
    WordPress::boot();
    WordPress::bootWooCommerce();
    WordPress::fake('wp_strip_all_tags', static fn (string $text): string => strip_tags($text));
    WordPress::fake('wc_price', static fn (float $value): string => '<span>'.number_format($value, 2).'</span>');
});

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
