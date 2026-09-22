<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Enums\ProductMeta;
use Modules\MeiliFacets\Support\WooCommerce;
use WC_Product;
use WC_Product_Grouped;
use WC_Product_Variable;
use WP_Post;

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

        $shown = $this->shownRange($product);

        if ($shown === null) {
            return [];
        }

        return [
            PriceField::Min->value => $shown[0],
            PriceField::Max->value => $shown[1],
            PriceField::OnSale->value => $this->isBilledOnSale($product),
        ];
    }

    /**
     * @return array{float, float}|null
     */
    private function shownRange(WC_Product $product): ?array
    {
        return match (true) {
            $product instanceof WC_Product_Variable => $this->variationRange($product),
            $product instanceof WC_Product_Grouped => $this->childrenRange($product),
            default => $this->singleRange($product),
        };
    }

    /**
     * @return array{float, float}|null
     */
    private function variationRange(WC_Product_Variable $product): ?array
    {
        $prices = $product->get_variation_prices(true)['price'];

        return $prices === [] ? null : [(float) current($prices), (float) end($prices)];
    }

    /**
     * @return array{float, float}|null
     */
    private function childrenRange(WC_Product_Grouped $product): ?array
    {
        $prices = array_filter(
            array_map($this->shownPrice(...), $product->get_visible_children()),
            static fn (?float $price): bool => $price !== null
        );

        return $prices === [] ? null : [min($prices), max($prices)];
    }

    /**
     * @return array{float, float}|null
     */
    private function singleRange(WC_Product $product): ?array
    {
        $price = $this->shownPrice($product);

        return $price === null ? null : [$price, $price];
    }

    /** An emptied price reads `''`, which `wc_get_price_to_display()` turns into 0. */
    private function shownPrice(WC_Product $product): ?float
    {
        if ($product->get_price() === '') {
            return null;
        }

        return (float) wc_get_price_to_display($product);
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
        $price = wc_format_decimal(get_post_meta($id, ProductMeta::Price->value, true));
        $sale = wc_format_decimal(get_post_meta($id, '_sale_price', true));

        return (bool) $sale && $price === $sale;
    }
}
