<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing\WooCommerce;

use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Enums\ProductMeta;
use Modules\MeiliFacets\Enums\SearchedMeta;

final readonly class WooCommerceIndexAttributes implements IndexAttributes
{
    public function exactlyMatched(): array
    {
        return [SearchedMeta::Sku->path()];
    }

    public function filterable(): array
    {
        return [
            ...ProductMeta::paths(),
            ...PriceField::paths(),
            DocumentField::Kind->value,
        ];
    }

    public function sortable(): array
    {
        return [
            PriceField::Min->path(),
            PriceField::Max->path(),
            DocumentField::InStock->value,
            DocumentField::MenuOrder->value,
        ];
    }

    public function displayed(): array
    {
        return [];
    }
}
