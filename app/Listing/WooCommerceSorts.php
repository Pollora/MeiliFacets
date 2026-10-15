<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Enums\CatalogOrder;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Enums\SortDirection;

final class WooCommerceSorts implements ProductSorts
{
    /** @var array<string, Sort>|null */
    private ?array $sorts = null;

    public function all(): array
    {
        return $this->sorts ??= [
            'price_asc' => new Sort(
                __('Price, low to high'),
                CatalogOrder::Price->expressions(SortDirection::Ascending),
            ),
            'price_desc' => new Sort(
                __('Price, high to low'),
                CatalogOrder::Price->expressions(SortDirection::Descending),
            ),
            'newest' => new Sort(
                __('New arrivals'),
                CatalogOrder::Date->expressions(SortDirection::Descending),
            ),
            'on_sale' => Sort::filtering(__('On sale'), SortFilter::whereTrue(PriceField::OnSale->path())),
        ];
    }
}
