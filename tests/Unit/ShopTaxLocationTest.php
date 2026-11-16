<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\WooCommerce\ShopTaxLocation;

it('only reads on a site without woocommerce', function (): void {
    if (function_exists('wc_get_product')) {
        $this->markTestSkipped('WooCommerce is loaded: another suite ran first.');
    }

    expect(new ShopTaxLocation()->during(static fn (): string => 'card'))->toBe('card');
});
