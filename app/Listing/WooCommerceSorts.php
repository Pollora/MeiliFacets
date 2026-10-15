<?php

declare(strict_types=1);

namespace Modules\MeiliFacets\Listing;

use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Enums\DocumentField;
use Modules\MeiliFacets\Enums\PriceField;
use Modules\MeiliFacets\Enums\SortDirection;

final class WooCommerceSorts implements ProductSorts
{
    /** @var array<string, Sort>|null */
    private ?array $sorts = null;

    public function all(): array
    {
        return $this->sorts ??= [
            // A product spanning 28–62 belongs at 28 going up and at 62 going down.
            'price_asc' => new Sort(__('Price, low to high'), [
                SortDirection::Ascending->of(PriceField::Min->path()),
            ]),
            'price_desc' => new Sort(__('Price, high to low'), [
                SortDirection::Descending->of(PriceField::Max->path()),
            ]),
            'newest' => new Sort(__('New arrivals'), [
                SortDirection::Descending->of(DocumentField::Date->value),
            ]),
            'on_sale' => Sort::filtering(__('On sale'), SortFilter::whereTrue(PriceField::OnSale->path())),
        ];
    }
}
