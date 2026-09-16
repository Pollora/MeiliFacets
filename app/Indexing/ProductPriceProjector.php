<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Support\WooCommerce;
use WC_Product;
use WC_Product_Grouped;
use WC_Product_Variable;
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
            PriceField::OnSale->value => $this->isBilledOnSale($product),
        ];
    }

    /** What WooCommerce lists as on sale: a variation lists its parent, a grouped product is never listed. */
    private function isBilledOnSale(WC_Product $product): bool
    {
        if ($product instanceof WC_Product_Grouped) {
            return false;
        }

        if (! $product instanceof WC_Product_Variable) {
            return $this->carriesSalePrice($product->get_id());
        }

        $children = $product->get_visible_children();
        update_meta_cache('post', $children);

        return array_any($children, $this->carriesSalePrice(...));
    }

    /**
     * The `onsale` lookup column, read off the metas: that table is refreshed after `_price`
     * is written, and the product is indexed in between.
     */
    private function carriesSalePrice(int $id): bool
    {
        $price = wc_format_decimal(get_post_meta($id, '_price', true));
        $sale = wc_format_decimal(get_post_meta($id, '_sale_price', true));

        return (bool) $sale && $price === $sale;
    }
}
