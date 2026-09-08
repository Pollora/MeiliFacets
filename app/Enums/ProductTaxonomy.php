<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Enums;

enum ProductTaxonomy: string
{
    case Category = 'product_cat';

    case Brand = 'product_brand';

    /** Not browsable: WooCommerce files catalogue flags here, `exclude-from-catalog` among them. */
    case Visibility = 'product_visibility';
}
