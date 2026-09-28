<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum ProductTaxonomy: string
{
    case Category = 'product_cat';

    case Brand = 'product_brand';

    /** Not browsable: WooCommerce files catalogue flags here, `exclude-from-catalog` among them. */
    case Visibility = 'product_visibility';

    case Type = 'product_type';

    case ShippingClass = 'product_shipping_class';

    case PosVisibility = 'pos_product_visibility';

    /**
     * Flags WooCommerce files as terms (`featured`, `simple`, `pos-hidden`…): filterable, never words a visitor
     * searches by.
     *
     * @return list<string>
     */
    public static function technical(): array
    {
        return [
            self::Visibility->value,
            self::Type->value,
            self::ShippingClass->value,
            self::PosVisibility->value,
        ];
    }
}
