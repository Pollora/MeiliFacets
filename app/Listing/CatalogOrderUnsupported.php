<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use RuntimeException;

final class CatalogOrderUnsupported extends RuntimeException
{
    public static function of(string $setting): self
    {
        return new self(
            "WooCommerce's default product sorting \"{$setting}\" has no equivalent the engine can sort on: "
            .'with no sort chosen, the product listing keeps the engine\'s order. '
            .'Pick custom ordering, name, latest or price under Appearance > Customize > WooCommerce > Product Catalog.'
        );
    }
}
