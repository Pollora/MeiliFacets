<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Search;

use Modules\MeiliFacets\Listing\Range;
use Modules\MeiliFacets\Support\WooCommerce;
use WC_Tax;

/**
 * A visitor types the price they see. When a shop stores prices without tax and
 * shows them with it, the two are not the same number, and the index holds the
 * stored one — so WooCommerce takes the tax back off before filtering
 * (`class-wc-query.php:800-810`). This does the same, through the same rates.
 */
final readonly class PriceTax
{
    private const string INCLUSIVE = 'incl';

    public static function excluding(Range $range): Range
    {
        if ($range->isEmpty() || ! self::applies()) {
            return $range;
        }

        $rates = WC_Tax::get_rates(
            (string) apply_filters('woocommerce_price_filter_widget_tax_class', '')
        );

        if ($rates === []) {
            return $range;
        }

        return new Range(self::withoutTax($range->min, $rates), self::withoutTax($range->max, $rates));
    }

    private static function applies(): bool
    {
        return WooCommerce::isActive()
            && wc_tax_enabled()
            && get_option('woocommerce_tax_display_shop') === self::INCLUSIVE
            && ! wc_prices_include_tax();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rates
     */
    private static function withoutTax(?float $bound, array $rates): ?float
    {
        return $bound === null
            ? null
            : $bound - (float) WC_Tax::get_tax_total(WC_Tax::calc_inclusive_tax($bound, $rates));
    }
}
