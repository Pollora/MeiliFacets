<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Tests\Unit;

use Modules\MeiliFacets\Indexing\ConfiguredIndexAttributes;
use Modules\MeiliFacets\Indexing\EmptyIndexAttributes;
use Modules\MeiliFacets\Indexing\WooCommerce\WooCommerceIndexAttributes;

/**
 * @return list<string>
 */
function indexAttributesFilterable(string $prefix): array
{
    return array_values(array_filter(
        (new WooCommerceIndexAttributes)->filterable(),
        static fn (string $path): bool => str_starts_with($path, $prefix)
    ));
}

it('contributes nothing without a plugin', function (): void {
    $attributes = new EmptyIndexAttributes;

    expect($attributes->filterable())->toBe([]);
    expect($attributes->sortable())->toBe([]);
    expect($attributes->exactlyMatched())->toBe([]);
});

// `AB-1234` and `AB-1235` are two products, not a typo of each other.
it('matches the sku without typo tolerance', function (): void {
    expect((new WooCommerceIndexAttributes)->exactlyMatched())->toBe(['metas._sku']);
});

it('declares the woocommerce metas under their document path', function (): void {
    expect(indexAttributesFilterable('metas.'))->toBe(['metas._price', 'metas._stock_status']);
});

// None of the three exists in `postmeta`: the module computes them.
it('declares the price interval outside the metas', function (): void {
    expect(indexAttributesFilterable('price.'))->toBe(['price.min', 'price.max', 'price.onsale']);
});

// Ascending reads the low end, descending the high one — see `WooCommerceSorts`.
it('sorts on both ends of the price interval on the stock and on the shop order', function (): void {
    expect((new WooCommerceIndexAttributes)->sortable())->toBe(['price.min', 'price.max', 'in_stock', 'menu_order', 'total_sales', 'average_rating', 'rating_count', 'ID']);
});

it('filters on the kind of document', function (): void {
    expect((new WooCommerceIndexAttributes)->filterable())->toContain('document_kind');
});

it('exposes nothing beyond the module by default', function (): void {
    expect((new EmptyIndexAttributes)->displayed())->toBe([]);
    expect((new WooCommerceIndexAttributes)->displayed())->toBe([]);
});

it('adds the fields a project declared', function (): void {
    $attributes = new ConfiguredIndexAttributes(new WooCommerceIndexAttributes, ['post_excerpt', 'metas._sku']);

    expect($attributes->displayed())->toBe(['post_excerpt', 'metas._sku']);
});

it('leaves the other declarations untouched', function (): void {
    $attributes = new ConfiguredIndexAttributes(new WooCommerceIndexAttributes, ['post_excerpt']);

    $woocommerce = new WooCommerceIndexAttributes;

    expect($attributes->filterable())->toBe($woocommerce->filterable());
    expect($attributes->sortable())->toBe($woocommerce->sortable());
    expect($attributes->exactlyMatched())->toBe((new WooCommerceIndexAttributes)->exactlyMatched());
});
