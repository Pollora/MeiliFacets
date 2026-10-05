<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

/** The lists `WC_Product_Variable::get_variation_prices()` files its prices under. */
enum VariationPrices: string
{
    case Active = 'price';
}
