<?php

declare(strict_types=1);

// Defining `wc_get_product()` is what tells the module WooCommerce is active: loaded by `WordPress::bootWooCommerce()`,
// from a test that runs in its own process only.

// Same long-tail stubs as `wordpress-functions.php`.
foreach ([
    'wc_get_product',
    'wc_price',
    'get_woocommerce_currency_symbol',
    'get_woocommerce_price_format',
    'wc_get_price_decimals',
    'wc_get_price_decimal_separator',
    'wc_get_price_thousand_separator',
    'wc_get_price_to_display',
    'wc_format_decimal',
    'taxonomy_is_product_attribute',
    'wc_variation_attribute_name',
    'wc_attribute_taxonomy_name',
    'WC',
    'wc_get_attribute_taxonomy_names',
    'wc_get_container',
    'wc_get_loop_prop',
    'wc_get_default_products_per_row',
    'wc_get_default_product_rows_per_page',
    'wc_products_array_filter_visible_grouped',
    'is_filtered',
] as $name) {
    if (! function_exists($name)) {
        eval("function {$name}(mixed ...\$arguments): mixed { return Modules\\MeiliFacets\\Tests\\Unit\\Doubles\\WordPress::call('{$name}', \$arguments); }");
    }
}
