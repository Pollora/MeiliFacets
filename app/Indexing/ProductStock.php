<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\CardField;
use Modules\MeiliFacets\Support\WooCommerce;
use WC_Product;
use WP_Post;

/** Flags the card of a product WooCommerce holds out of stock, whatever its type. */
final readonly class ProductStock
{
    /**
     * @return array<string, true>
     */
    public function project(WP_Post $post): array
    {
        if (! WooCommerce::isActive()) {
            return [];
        }

        $product = wc_get_product($post);
        $isOutOfStock = $product instanceof WC_Product && ! $product->is_in_stock();

        return $isOutOfStock ? [CardField::OutOfStock->value => true] : [];
    }
}
