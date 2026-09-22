<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Closure;
use Modules\MeiliFacets\Support\WooCommerce;

/** `WC_Tax::get_tax_location()` taxes at the address of whoever is in session, an admin's quick edit included. */
final readonly class ShopTaxLocation
{
    private const string FILTER = 'woocommerce_get_tax_location';

    /**
     * @template T
     *
     * @param  Closure(): T  $read
     * @return T
     */
    public function during(Closure $read): mixed
    {
        if (! WooCommerce::isActive()) {
            return $read();
        }

        $shopAddress = $this->shopAddress(...);
        add_filter(self::FILTER, $shopAddress);

        try {
            return $read();
        } finally {
            remove_filter(self::FILTER, $shopAddress);
        }
    }

    /**
     * @return array{string, string, string, string}
     */
    private function shopAddress(): array
    {
        $countries = WC()->countries;

        return [
            $countries->get_base_country(),
            $countries->get_base_state(),
            $countries->get_base_postcode(),
            $countries->get_base_city(),
        ];
    }
}
