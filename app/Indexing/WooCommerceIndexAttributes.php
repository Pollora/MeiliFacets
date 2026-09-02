<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Indexing;

use Modules\MeiliFacets\Contracts\IndexAttributes;
use Modules\MeiliFacets\Enums\ProductMeta;

final readonly class WooCommerceIndexAttributes implements IndexAttributes
{
    /**
     * @return list<string>
     */
    public function filterable(): array
    {
        return ProductMeta::paths();
    }

    /**
     * @return list<string>
     */
    public function sortable(): array
    {
        return [ProductMeta::Price->path()];
    }
}
