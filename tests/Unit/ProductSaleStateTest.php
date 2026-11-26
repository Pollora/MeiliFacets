<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\ProductSaleState;

use Modules\MeiliFacets\Indexing\WooCommerce\ProductPriceProjector;
use Modules\MeiliFacets\Tests\Unit\Doubles\WordPress;
use WC_Product;
use WC_Product_Variable;
use WP_Post;

beforeEach(function (): void {
    WordPress::boot();
    WordPress::bootWooCommerce();
    WordPress::fake('wc_format_decimal', static fn (mixed $value): string => (string) $value);
    WordPress::fake('wc_get_price_to_display', 8.0);
    WordPress::fake('update_meta_cache', null);
});

/**
 * @param  array<int, array{_price: string, _sale_price: string}>  $metas
 */
function fakeMetas(array $metas): void
{
    WordPress::fake('get_post_meta', static function (int $id, string $key, bool $single = false) use ($metas): mixed {
        $value = $metas[$id][$key] ?? '';

        return $single ? $value : [$value];
    });
}

it('is on sale only when a sale price is set and it is the price billed', function (string $price, string $sale, bool $onSale): void {
    fakeMetas([4 => ['_price' => $price, '_sale_price' => $sale]]);
    WordPress::fake('wc_get_product', new WC_Product(['id' => 4, 'price' => '8']));

    expect(new ProductPriceProjector()->project(new WP_Post)['onsale'])->toBe($onSale);
})->with([
    'sale price billed' => ['8', '8', true],
    'sale price not billed, as after the sale ended' => ['10', '8', false],
    'no sale price and no price' => ['', '', false],
    'no sale price' => ['8', '', false],
]);

it('reads the sale state of a variable product from its variations, their metas loaded at once', function (): void {
    fakeMetas([21 => ['_price' => '10', '_sale_price' => ''], 22 => ['_price' => '8', '_sale_price' => '8']]);
    $product = new class(['prices' => [21 => '10', 22 => '8']]) extends WC_Product_Variable
    {
        /** @return list<int> */
        public function get_visible_children(): array
        {
            return [21, 22];
        }
    };
    WordPress::fake('wc_get_product', $product);

    expect(new ProductPriceProjector()->project(new WP_Post)['onsale'])->toBeTrue();

    $primed = array_values(array_filter(WordPress::$log, static fn (array $call): bool => $call[0] === 'update_meta_cache'));
    expect(array_column($primed, 1))->toBe([['post', [21, 22]]]);
});
