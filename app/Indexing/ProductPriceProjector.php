<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Support\WooCommerce;
use WC_Product;
use WP_Post;

/**
 * The interval a product covers, so a range filter can test an overlap the way
 * WooCommerce does, instead of comparing against one price.
 */
final readonly class ProductPriceProjector
{
    /**
     * @return array<string, float|bool>
     */
    public function project(WP_Post $post): array
    {
        if (! WooCommerce::isActive()) {
            return [];
        }

        $product = wc_get_product($post);

        if (! $product instanceof WC_Product) {
            return [];
        }

        // One row per distinct child price, written sorted by `sync_price()` — which
        // is why the ends are the bounds. A product with no price has none.
        $prices = (array) get_post_meta($post->ID, '_price', false);

        if ($prices === []) {
            return [];
        }

        return [
            PriceField::Min->value => (float) reset($prices),
            PriceField::Max->value => (float) end($prices),
            PriceField::OnSale->value => $product->is_on_sale(),
        ];
    }
}
