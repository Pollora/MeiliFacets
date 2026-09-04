<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

final readonly class PageSize
{
    private const string WOOCOMMERCE_FILTER = 'loop_shop_per_page';

    private const string WORDPRESS_OPTION = 'posts_per_page';

    /**
     * WooCommerce only fills `wc_get_loop_prop('per_page')` from the main query,
     * so reading the filter directly is what keeps WP_Query out of the way.
     */
    public static function forProducts(): int
    {
        $default = wc_get_default_products_per_row() * wc_get_default_product_rows_per_page();

        return (int) apply_filters(self::WOOCOMMERCE_FILTER, $default);
    }

    public static function forPosts(): int
    {
        return (int) get_option(self::WORDPRESS_OPTION);
    }
}
