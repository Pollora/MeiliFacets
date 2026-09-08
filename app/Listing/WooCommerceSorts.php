<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Enums\ProductMeta;

final class WooCommerceSorts implements ProductSorts
{
    /** @var array<string, Sort>|null */
    private ?array $sorts = null;

    public function all(): array
    {
        return $this->sorts ??= [
            'price_asc' => new Sort(__('Price, low to high'), [ProductMeta::Price->path().':asc']),
            'price_desc' => new Sort(__('Price, high to low'), [ProductMeta::Price->path().':desc']),
            'newest' => new Sort(__('New arrivals'), ['post_date:desc']),
        ];
    }
}
