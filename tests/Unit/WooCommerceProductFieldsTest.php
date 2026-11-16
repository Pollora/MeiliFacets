<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit\WooCommerceProductFields;

use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceProductFields;

it('ranks the brand then the category then the sku', function (): void {
    $fields = new WooCommerceProductFields(static fn (): bool => true);

    expect($fields->all())->toBe(['labels.product_brand', 'labels.product_cat', 'metas._sku']);
});

it('ranks nothing without woocommerce', function (): void {
    expect(new WooCommerceProductFields(static fn (): bool => false)->all())->toBe([]);
});

/** A hook wired before plugins load keeps its instance for the whole request (R-171). */
it('asks whether woocommerce is there on every read', function (): void {
    $pluginIsActive = false;
    $fields = new WooCommerceProductFields(static function () use (&$pluginIsActive): bool {
        return $pluginIsActive;
    });

    expect($fields->all())->toBe([]);

    $pluginIsActive = true;

    expect($fields->all())->toHaveCount(3);
});
