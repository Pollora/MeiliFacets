<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Enums\PriceField;

final class WooCommerceSorts implements ProductSorts
{
    /** @var array<string, Sort>|null */
    private ?array $sorts = null;

    public function all(): array
    {
        return $this->sorts ??= [
            // A product spanning 28–62 belongs at 28 going up and at 62 going down.
            'price_asc' => new Sort(__('Price, low to high'), [PriceField::Min->path().':asc']),
            'price_desc' => new Sort(__('Price, high to low'), [PriceField::Max->path().':desc']),
            'newest' => new Sort(__('New arrivals'), ['post_date:desc']),
        ];
    }
}
