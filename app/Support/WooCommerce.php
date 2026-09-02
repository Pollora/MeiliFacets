<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Support;

final class WooCommerce
{
    public static function isActive(): bool
    {
        return function_exists('wc_get_product');
    }
}
